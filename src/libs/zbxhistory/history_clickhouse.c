/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "zbxhistory.h"
#include "zbxself.h"
#include "history.h"

/* curl_multi_wait() is supported starting with version 7.28.0 (0x071c00) */
#if defined(HAVE_LIBCURL) && LIBCURL_VERSION_NUM >= 0x071c00 || 1
size_t	DCconfig_get_itemids_by_valuetype( int value_type, zbx_vector_uint64_t *vector_itemids);
int	zbx_vc_simple_add(zbx_uint64_t itemids, zbx_history_record_t *record);


extern char	*CONFIG_HISTORY_STORAGE_URL;
extern int	CONFIG_HISTORY_STORAGE_PIPELINES;
extern char *CONFIG_HISTORY_STORAGE_DB_NAME;
extern int CONFIG_CLICKHOUSE_SAVE_HOST_AND_METRIC_NAME;
extern int CONFIG_CLICKHOUSE_DISABLE_NS_VALUE;
extern char *CONFIG_CLICKHOUSE_USERNAME;
extern char *CONFIG_CLICKHOUSE_PASSWORD;
extern int CONFIG_SERVER_STARTUP_TIME;
extern int CONFIG_CLICKHOUSE_VALUECACHE_FILL_TIME;
extern int CONFIG_CLICKHOUSE_PRELOAD_VALUES;

typedef struct
{
	char	*url;
	char	*buf;
}
zbx_clickhouse_data_t;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
zbx_httppage_t;

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	zbx_httppage_t	*page = (zbx_httppage_t	*)userdata;
	zbx_strncpy_alloc(&page->data, &page->alloc, &page->offset, ptr, r_size);

	return r_size;
}

static history_value_t	history_str2value(char *str, unsigned char value_type)
{
	history_value_t	value;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
			memset(value.log, 0, sizeof(zbx_log_value_t));
			value.log->value = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			value.str = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			value.dbl = atof(str);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(value.ui64, str);
			break;
	}

	return value;
}

static void	clickhouse_log_error(CURL *handle, CURLcode error, const char *errbuf,zbx_httppage_t *page_r)
{
	long	http_code;

	if (CURLE_HTTP_RETURNED_ERROR == error)
	{
		curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);
		if (0 != page_r->offset)
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from clickhouse, HTTP error: %ld, message: %s",
					http_code, page_r->data);
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from clickhouse, HTTP error: %ld", http_code);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get values from clickhousesearch: %s",
				'\0' != *errbuf ? errbuf : curl_easy_strerror(error));
	}
}

/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_close                                                          *
 *                                                                                  *
 * Purpose: closes connection and releases allocated resources                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	clickhouse_close(zbx_history_iface_t *hist)
{
	zbx_clickhouse_data_t	*data = (zbx_clickhouse_data_t *)hist->data;

	zbx_free(data->buf);
}


/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_destroy                                                        *
 *                                                                                  *
 * Purpose: destroys history storage interface                                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	clickhouse_destroy(zbx_history_iface_t *hist)
{
	zbx_clickhouse_data_t	*data = (zbx_clickhouse_data_t *)hist->data;

	clickhouse_close(hist);

	zbx_free(data->url);
	zbx_free(data);
}
/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_get_values                                                     *
 *                                                                                  *
 * Purpose: gets item history data from history storage                             *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              itemid  - [IN] the itemid                                           *
 *              start   - [IN] the period start timestamp                           *
 *              count   - [IN] the number of values to read                         *
 *              end     - [IN] the period end timestamp                             *
 *              values  - [OUT] the item history data values                        *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	clickhouse_get_agg_values(zbx_history_iface_t *hist, zbx_uint64_t itemid, int start, int end, int aggregates,
		char **buffer)
{
	const char		*__function_name = "clickhouse_get_agg_values";
	int valuecount=0;

	zbx_clickhouse_data_t	*data = (zbx_clickhouse_data_t *)hist->data;
	size_t			url_alloc = 0, url_offset = 0;
    
	CURLcode		err;
	CURL	*handle = NULL;
	
	struct curl_slist	*curl_headers = NULL;
	
    char  errbuf[CURL_ERROR_SIZE];
    char	*sql_buffer=NULL;
    size_t			buf_alloc = 0, buf_offset = 0;
    zbx_httppage_t page_r;
	int ret = FAIL;
    char *field_name="value";
	//zbx_history_record_t	hr;
	

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (end < start || aggregates <1 ) {
		zabbix_log(LOG_LEVEL_WARNING,"%s: wrong params requested: start:%ld end:%ld, aggregates: %ld",__func__,start,end,aggregates);
		goto out;
	}
	
	if ( hist->value_type == ITEM_VALUE_TYPE_FLOAT) {
		field_name="value_dbl";
	}

    bzero(&page_r,sizeof(zbx_httppage_t));

	if (NULL == (handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		goto out;
	} 

	
	zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, 
	"SELECT itemid, \
		intDiv( (toUnixTimestamp(clock)-%ld)*%ld, %ld) as i,\
		max(toUnixTimestamp(clock)) as clcck ,\
		avg(%s) as avg, \
		count(%s) as count, \
		min(%s) as min , \
		max(%s) as max \
	FROM %s.history h \
	WHERE clock BETWEEN %ld AND %ld AND \
	itemid = %ld \
	GROUP BY itemid, i \
	ORDER BY i \
	FORMAT JSON", start,aggregates, end-start, 
				field_name,field_name,field_name,field_name,
				CONFIG_HISTORY_STORAGE_DB_NAME, start, end, itemid);

	//zabbix_log(LOG_LEVEL_INFORMATION, "CLICKHOUSE: sending query to clickhouse: %s", sql_buffer);

	curl_easy_setopt(handle, CURLOPT_URL, data->url);
	curl_easy_setopt(handle, CURLOPT_POSTFIELDS, sql_buffer);
	curl_easy_setopt(handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(handle, CURLOPT_WRITEDATA, &page_r);
	curl_easy_setopt(handle, CURLOPT_HTTPHEADER, curl_headers);
	curl_easy_setopt(handle, CURLOPT_FAILONERROR, 1L);
	curl_easy_setopt(handle, CURLOPT_ERRORBUFFER, errbuf);

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", data->url, sql_buffer);

	page_r.offset = 0;
	*errbuf = '\0';

	if (CURLE_OK != (err = curl_easy_perform(handle)))
	{
		clickhouse_log_error(handle, err, errbuf,&page_r);
        zabbix_log(LOG_LEVEL_WARNING, "Failed query '%s'", sql_buffer);
		goto out;
	}

    zabbix_log(LOG_LEVEL_DEBUG, "Recieved from clickhouse: %s", page_r.data);
	//buffer=zbx_strdup(NULL,page_r.data);
		
    struct zbx_json_parse	jp, jp_row, jp_data;
	const char		*p = NULL;
	size_t offset=0, allocd=0;
    
    zbx_json_open(page_r.data, &jp);

    if (SUCCEED == zbx_json_brackets_by_name(&jp, "data", &jp_data) ) {
		//adding one more byte for the trailing zero
		size_t buf_size=jp_data.end-jp_data.start+1;
		//zabbix_log(LOG_LEVEL_INFORMATION,"HIST: Will need %ld buffer1", buf_size);
		zbx_strncpy_alloc(buffer,&allocd,&offset,jp_data.start,buf_size);
		
		
		//lets fix the field naming
		//this better must be accomplished by fixing sql so that column name would be clock,
		//but so far i haven't done it yet //todo:
		//this code changes all clcck to clock, since i am sure
		//there is only numerical data and fixed column name, must work ok
		//does evth in one pass
		char *pos=*buffer;
		while (NULL != (pos=strstr(pos,"clcck"))) {
			 pos+=2;
			 pos[0]='o';
		}

		ret=SUCCEED;
	}
  
out: 

	clickhouse_close(hist);
	curl_easy_cleanup(handle);
	curl_slist_free_all(curl_headers);
    zbx_free(sql_buffer);
    zbx_free(page_r.data);


	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_get_values                                                     *
 *                                                                                  *
 * Purpose: gets item history data from history storage                             *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              itemid  - [IN] the itemid                                           *
 *              start   - [IN] the period start timestamp                           *
 *              count   - [IN] the number of values to read                         *
 *              end     - [IN] the period end timestamp                             *
 *              values  - [OUT] the item history data values                        *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	clickhouse_get_values(zbx_history_iface_t *hist, zbx_uint64_t itemid, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	const char		*__function_name = "clickhouse_get_values";
	int valuecount=0;

	zbx_clickhouse_data_t	*data = (zbx_clickhouse_data_t *)hist->data;
	size_t			url_alloc = 0, url_offset = 0;
    
	CURLcode		err;
	CURL	*handle = NULL;
	
	struct curl_slist	*curl_headers = NULL;
	
    char  errbuf[CURL_ERROR_SIZE];
    char	*sql_buffer=NULL;
    size_t			buf_alloc = 0, buf_offset = 0;
    zbx_httppage_t page_r;
 
	zbx_history_record_t	hr;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

    bzero(&page_r,sizeof(zbx_httppage_t));

	
    if (time(NULL)- CONFIG_CLICKHOUSE_VALUECACHE_FILL_TIME < CONFIG_SERVER_STARTUP_TIME) {
		zabbix_log(LOG_LEVEL_DEBUG, "waiting for cache load, exiting");
        goto out;
	}

	if (NULL == (handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		goto out;
	} 
	
	 zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, 
			"SELECT  toUInt32(clock) clock,value,value_dbl,value_str");

	if ( 0 == CONFIG_CLICKHOUSE_DISABLE_NS_VALUE) {
		zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, ",ns");
	}
	
	zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, " FROM %s.history_buffer WHERE itemid=%ld ",
		CONFIG_HISTORY_STORAGE_DB_NAME,itemid);

	if (1 == end-start) {
		zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "AND clock = %d ", end);
	} else {
		if (0 < start) {
			zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "AND clock > %d ", start);
		}
		if (0 < end ) {
			zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "AND clock <= %d ", end);
		}
	}

	zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "ORDER BY clock DESC ");

	if (0 < count) 
	{
	    zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "LIMIT %d ", count);
	}

    zbx_snprintf_alloc(&sql_buffer, &buf_alloc, &buf_offset, "format JSON ");

	zabbix_log(LOG_LEVEL_DEBUG, "CLICKHOUSE: sending query to clickhouse: %s", sql_buffer);

	curl_easy_setopt(handle, CURLOPT_URL, data->url);
	curl_easy_setopt(handle, CURLOPT_POSTFIELDS, sql_buffer);
	curl_easy_setopt(handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(handle, CURLOPT_WRITEDATA, &page_r);
	curl_easy_setopt(handle, CURLOPT_HTTPHEADER, curl_headers);
	curl_easy_setopt(handle, CURLOPT_FAILONERROR, 1L);
	curl_easy_setopt(handle, CURLOPT_ERRORBUFFER, errbuf);

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", data->url, sql_buffer);

	page_r.offset = 0;
	*errbuf = '\0';

	if (CURLE_OK != (err = curl_easy_perform(handle)))
	{
		clickhouse_log_error(handle, err, errbuf,&page_r);
        zabbix_log(LOG_LEVEL_WARNING, "Failed query '%s'", sql_buffer);
		goto out;
	}

    zabbix_log(LOG_LEVEL_DEBUG, "Recieved from clickhouse: %s", page_r.data);
		
    struct zbx_json_parse	jp, jp_row, jp_data;
	const char		*p = NULL;
    
    zbx_json_open(page_r.data, &jp);
    zbx_json_brackets_by_name(&jp, "data", &jp_data);
    
    while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
        char *itemid=NULL;
        char *clck = NULL, *ns = NULL, *value = NULL, *value_dbl = NULL, *value_str = NULL;
        size_t clck_alloc=0, ns_alloc = 0, value_alloc = 0, value_dbl_alloc = 0, value_str_alloc = 0;
        struct zbx_json_parse	jp_row;

        if (SUCCEED == zbx_json_brackets_open(p, &jp_row)) {
			
            if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "clock", &clck, &clck_alloc) &&
                SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value", &value, &value_alloc) &&
                SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value_dbl", &value_dbl, &value_dbl_alloc) &&
                SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value_str", &value_str, &value_str_alloc)) 
            {
               
			   	if ( 0 == CONFIG_CLICKHOUSE_DISABLE_NS_VALUE &&
					 SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "ns", &ns, &ns_alloc) ) {
							hr.timestamp.ns = atoi(ns); 
				} else hr.timestamp.ns = 0;

               	hr.timestamp.sec = atoi(clck);
				zabbix_log(LOG_LEVEL_DEBUG,"CLICKHOSUE read: Clock: %s, ns: %s, value: %s, value_dbl: %s, value_str:%s ",clck,ns,value,value_dbl,value_str);

                switch (hist->value_type)
				{
					case ITEM_VALUE_TYPE_UINT64:
						zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as UINT64 %s",value);
			    		hr.value = history_str2value(value, hist->value_type);
						zbx_vector_history_record_append_ptr(values, &hr);
						break;

					case ITEM_VALUE_TYPE_FLOAT: 
						zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as DBL field %s",value_dbl);
			    		hr.value = history_str2value(value_dbl, hist->value_type);
                        zbx_vector_history_record_append_ptr(values, &hr);
						break;
					case ITEM_VALUE_TYPE_STR:
					case ITEM_VALUE_TYPE_TEXT:

						zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as STR/TEXT type %s",value_str);
						hr.value = history_str2value(value_str, hist->value_type);
                        zbx_vector_history_record_append_ptr(values, &hr);
                        break;

					case ITEM_VALUE_TYPE_LOG:
						//todo: does server really need's to read logs????
                        break;
				}				
				
				valuecount++;
			} 
            
        } else {
            zabbix_log(LOG_LEVEL_DEBUG,"CLICCKHOUSE: Couldn't parse JSON row: %s",p);
        };

		if ( !valuecount) zabbix_log(LOG_LEVEL_DEBUG,"No data returned form request");
        zbx_free(clck);
        zbx_free(ns);
        zbx_free(value);
        zbx_free(value_dbl);
        zbx_free(value_str);            
    } 
out:
	clickhouse_close(hist);
	curl_easy_cleanup(handle);
	curl_slist_free_all(curl_headers);
    zbx_free(sql_buffer);
    zbx_free(page_r.data);

	zbx_vector_history_record_sort(values, (zbx_compare_func_t)zbx_history_record_compare_desc_func);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	//retrun succeeds ander any circumstances 
	//since otherwise history sincers will try to repeate the query 
	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_add_values                                                     *
 *                                                                                  *
 * Purpose: sends history data to the storage                                       *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              history - [IN] the history data vector (may have mixed value types) *
 *                                                                                  *
 ************************************************************************************/
static int	clickhouse_add_values(zbx_history_iface_t *hist, const zbx_vector_ptr_t *history)
{
	const char	*__function_name = "clickhouse_add_values";

	zbx_clickhouse_data_t	*data = (zbx_clickhouse_data_t *)hist->data;
	int			i,j, num = 0;
	ZBX_DC_HISTORY		*h;
	struct zbx_json		json_idx, json;
	size_t			buf_alloc = 0, buf_offset = 0;
	
    char *sql_buffer=NULL;	
	size_t sql_alloc=0, sql_offset=0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
    
	zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,"INSERT INTO %s.history_buffer (day,itemid,clock,value,value_dbl,value_str", CONFIG_HISTORY_STORAGE_DB_NAME);

	if ( 0 == CONFIG_CLICKHOUSE_DISABLE_NS_VALUE ) {
		zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",ns");
	}
	
	if ( 1 == CONFIG_CLICKHOUSE_SAVE_HOST_AND_METRIC_NAME ) {
		zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",hostname, itemname");
	}
	

	zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,") VALUES");

	for (i = 0; i < history->values_num; i++)
	{
		h = (ZBX_DC_HISTORY *)history->values[i];
			
		if (hist->value_type != h->value_type)	
			continue;
		
		//common part
		zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,"(CAST(%d as date) ,%ld,%d",
				h->ts.sec,h->itemid,h->ts.sec);
    	
		//type-dependent part
		if (ITEM_VALUE_TYPE_UINT64 == h->value_type) 
	           zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",%ld,0,''",h->value.ui64);
    	
		if (ITEM_VALUE_TYPE_FLOAT == h->value_type) 
           zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",0,%f,''",h->value.dbl);
        

		if (ITEM_VALUE_TYPE_STR == h->value_type || ITEM_VALUE_TYPE_TEXT == h->value_type ) {
		    		
            //todo: make more sensible string quotation
            for (j = 0; j < strlen(h->value.str); j++) {
		        if ('\'' == h->value.str[j]) { 
				    h->value.str[j]=' ';
			    }
			}
		    zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",0,0,'%s'",h->value.str);
		}
		//todo: log writing support: must be done in separate table unlike other values
		//if (ITEM_VALUE_TYPE_LOG == h->value_type)
		//{
		//    const zbx_log_value_t	*log;
		//    log = h->value.log;
		//}

		if ( 0 == CONFIG_CLICKHOUSE_DISABLE_NS_VALUE) {
			zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",%d", h->ts.ns);
		}
		
		if ( 1 == CONFIG_CLICKHOUSE_SAVE_HOST_AND_METRIC_NAME ) {
			zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,",'%s','%s'", h->host_name, h->item_key);
		}
		
		zbx_snprintf_alloc(&sql_buffer,&sql_alloc,&sql_offset,"),");

		num++;
	}

	if (num > 0)
	{ 
    
		zbx_httppage_t	page_r;
		bzero(&page_r,sizeof(zbx_httppage_t));
		struct curl_slist	*curl_headers = NULL;
		char  errbuf[CURL_ERROR_SIZE];
		CURLcode		err;
		CURL	*handle = NULL;
		
		if (NULL == (handle = curl_easy_init()))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		} else {

			curl_easy_setopt(handle, CURLOPT_URL, data->url);
			curl_easy_setopt(handle, CURLOPT_POSTFIELDS, sql_buffer);
			curl_easy_setopt(handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
			curl_easy_setopt(handle, CURLOPT_WRITEDATA, page_r);
			curl_easy_setopt(handle, CURLOPT_HTTPHEADER, curl_headers);
			curl_easy_setopt(handle, CURLOPT_FAILONERROR, 1L);
			curl_easy_setopt(handle, CURLOPT_ERRORBUFFER, errbuf);
	
			if (CURLE_OK != (err = curl_easy_perform(handle)))
			{
				clickhouse_log_error(handle, err, errbuf,&page_r);
        		zabbix_log(LOG_LEVEL_WARNING, "Failed query '%s'", sql_buffer);
	
			} else {
				zabbix_log(LOG_LEVEL_DEBUG, "CLICKHOUSE: succeeded query: %s",sql_buffer);
			}
		}
		
	 	zbx_free(page_r.data);
		curl_slist_free_all(curl_headers);
		curl_easy_cleanup(handle);
	}

	zbx_free(sql_buffer);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return num;
}

/************************************************************************************
 *                                                                                  *
 * Function: clickhouse_flush                                                          *
 *                                                                                  *
 * Purpose: flushes the history data to storage                                     *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *                                                                                  *
 * Comments: This function will try to flush the data until it succeeds or          *
 *           unrecoverable error occurs                                             *
 *                                                                                  *
 ************************************************************************************/
static int	clickhouse_flush(zbx_history_iface_t *hist)
{
	ZBX_UNUSED(hist);
	return SUCCEED;
}


static int zbx_history_add_vc(char* url, int value_type, char *query) {
	
	CURL	*handle = NULL;
	zbx_httppage_t	page_r;
	bzero(&page_r,sizeof(zbx_httppage_t));
	struct curl_slist	*curl_headers = NULL;
	char  errbuf[CURL_ERROR_SIZE];
	CURLcode		err;
	zbx_history_record_t	hr;
	int valuecount = 0;
	const char		*p = NULL;

	if (NULL == (handle = curl_easy_init())) {
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		goto out;
	};

	page_r.offset = 0;
	*errbuf = '\0';

	curl_easy_setopt(handle, CURLOPT_URL, url);
	curl_easy_setopt(handle, CURLOPT_POSTFIELDS, query);
	curl_easy_setopt(handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(handle, CURLOPT_WRITEDATA, &page_r);
	curl_easy_setopt(handle, CURLOPT_HTTPHEADER, curl_headers);
	curl_easy_setopt(handle, CURLOPT_FAILONERROR, 1L);
	curl_easy_setopt(handle, CURLOPT_ERRORBUFFER, errbuf);


	if (CURLE_OK != (err = curl_easy_perform(handle))) {
			clickhouse_log_error(handle, err, errbuf,&page_r);
        	zabbix_log(LOG_LEVEL_WARNING, "Failed query %s",query);
			goto out;
	}	

	if (NULL != page_r.data) {
    	zabbix_log(LOG_LEVEL_DEBUG, "Query copleted, filling value cache");
		
		struct zbx_json_parse	jp, jp_row, jp_data;
		
    	
		zbx_json_open(page_r.data, &jp);
    	zbx_json_brackets_by_name(&jp, "data", &jp_data);
    
    	while (NULL != (p = zbx_json_next(&jp_data, p))) {
        	
			char *clck = NULL,  *value = NULL, *value_dbl = NULL, *value_str = NULL , *itemid_str = NULL;
        	size_t clck_alloc=0,  value_alloc = 0, value_dbl_alloc = 0, value_str_alloc = 0, itemid_alloc = 0;
        	struct zbx_json_parse	jp_row;
			zbx_uint64_t itemid=0;
			
			if (SUCCEED == zbx_json_brackets_open(p, &jp_row)) {
					
            	if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "itemid", &itemid_str, &itemid_alloc) &&
					SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "clock", &clck, &clck_alloc) &&
           			SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value", &value, &value_alloc) &&
                	SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value_dbl", &value_dbl, &value_dbl_alloc) &&
                	SUCCEED == zbx_json_value_by_name_dyn(&jp_row, "value_str", &value_str, &value_str_alloc)) {
               
			   		hr.timestamp.sec = atoi(clck);
					itemid=atoi(itemid_str);
			
                	switch (value_type) {
						case ITEM_VALUE_TYPE_UINT64:
							zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as UINT64 %s",value);
			    			hr.value = history_str2value(value, value_type);
							break;

						case ITEM_VALUE_TYPE_FLOAT: 
							zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as DBL field %s",value_dbl);
				    		hr.value = history_str2value(value_dbl, value_type);
							break;
					
						case ITEM_VALUE_TYPE_STR:
						case ITEM_VALUE_TYPE_TEXT:
							zabbix_log(LOG_LEVEL_DEBUG, "Parsed  as STR/TEXT type %s",value_str);
							hr.value = history_str2value(value_str, value_type);
                        	break;

						default:
							//todo: does server really need's to read logs????
							goto out;
                    }	
						
					
					if (FAIL == zbx_vc_simple_add(itemid,&hr)) {
						zabbix_log(LOG_LEVEL_INFORMATION,"Couldn't add value to vc after %ld items", valuecount);
						
						if ( 0 == CONFIG_CLICKHOUSE_VALUECACHE_FILL_TIME ) {
							//in case if any prefetching has failed, then 
							//and user set zerp fill time, them we assume 
							//system will suffer from clickhouse hammering, 
							//so we set ip up to avoid reading clickhouse for the 
							//next 24 hors
							CONFIG_CLICKHOUSE_VALUECACHE_FILL_TIME = 24 * 3600;
						} 
					} else {
						valuecount++;
					}
										
				}
			}

			zbx_free(itemid_str);
			zbx_free(clck);
        	zbx_free(value);
        	zbx_free(value_dbl);
        	zbx_free(value_str);            
             
        }
	} else {
        zabbix_log(LOG_LEVEL_DEBUG,"CLICCKHOUSE: Couldn't parse JSON row: %s",p);
	};

out:
	zbx_free(page_r.data);
	curl_slist_free_all(curl_headers);
	curl_easy_cleanup(handle);
	zabbix_log(LOG_LEVEL_INFORMATION,"History preload: %ld values loaded to the value cache", valuecount);
	return valuecount;
}


static int clickhouse_preload_values(zbx_history_iface_t *hist) {
	
	int valuecount=0;

	if (CONFIG_CLICKHOUSE_PRELOAD_VALUES > 0 ) {
		unsigned long rows_num;
		zbx_vector_uint64_t vector_itemids;
		zbx_vector_history_record_t values;
		size_t i=0;
		int k=0;
		char *query=NULL;
		size_t q_len = 0, q_offset = 0;
		zbx_clickhouse_data_t *data = hist->data;

		zbx_vector_uint64_create(&vector_itemids);
		

		zabbix_log(LOG_LEVEL_INFORMATION,"Prefetching items of type %d to value cache",hist->value_type);
		size_t items=DCconfig_get_itemids_by_valuetype( hist->value_type, &vector_itemids);
		zabbix_log(LOG_LEVEL_INFORMATION,"Got %ld items for the type",items);

		if ( items > 0 ) {
			
			while( i < vector_itemids.values_num ) {
					
				zbx_snprintf_alloc(&query,&q_len,&q_offset,"SELECT itemid, clock, value, value_dbl, value_str FROM %s.history_buffer WHERE (itemid IN  (",
						CONFIG_HISTORY_STORAGE_DB_NAME);

#define MAX_ITEMS_PER_QUERY 9000
#define MAX_QUERY_LENGTH 200*1024

				while (i-k < MAX_ITEMS_PER_QUERY && q_len < MAX_QUERY_LENGTH && i<vector_itemids.values[i]) {
					if ( i-k == 1 || ( 0==i && 0==k)) zbx_snprintf_alloc(&query,&q_len,&q_offset,"%ld",vector_itemids.values[i]);
					else zbx_snprintf_alloc(&query,&q_len,&q_offset,",%ld",vector_itemids.values[i]);
					i++;
				}
				
				zbx_snprintf_alloc(&query,&q_len,&q_offset,")) AND (day = today() OR day = today()-1 )	ORDER BY itemid ASC, clock DESC	LIMIT 10 BY itemid");
				zbx_snprintf_alloc(&query, &q_len, &q_offset, " format JSON ");

				zabbix_log(LOG_LEVEL_DEBUG,"Length of the query: '%ld'",strlen(query));
				zabbix_log(LOG_LEVEL_DEBUG,"History preloading: Perfroming query for items %ld - %ld out of %ld of type %d",k,i,vector_itemids.values_num, hist->value_type);
				//zabbix_log(LOG_LEVEL_INFORMATION,"query: %s",query);
				//zbx_history_fill_value_cache();
				valuecount += zbx_history_add_vc( data->url, hist->value_type, query);

				zbx_free(query);
				q_len=0;
				q_offset=0;

				k=i;
				i++;
			
			}

		}
		
		zbx_vector_uint64_destroy(&vector_itemids);

	}
	return valuecount;
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_clickhouse_init                                               *
 *                                                                                  *
 * Purpose: initializes history storage interface                                   *
 *                                                                                  *
 * Parameters:  hist       - [IN] the history storage interface                     *
 *              value_type - [IN] the target value type                             *
 *              error      - [OUT] the error message                                *
 *                                                                                  *
 * Return value: SUCCEED - the history storage interface was initialized            *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_clickhouse_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	zbx_clickhouse_data_t	*data;
	
	size_t alloc = 0, offset = 0;
	
	if (0 != curl_global_init(CURL_GLOBAL_ALL)) {
		*error = zbx_strdup(*error, "Cannot initialize cURL library");
		return FAIL;
	}

	data = (zbx_clickhouse_data_t *)zbx_malloc(NULL, sizeof(zbx_clickhouse_data_t));
	
	memset(data, 0, sizeof(zbx_clickhouse_data_t));
	
	if (NULL != CONFIG_CLICKHOUSE_USERNAME) {
	
		zbx_snprintf_alloc(&data->url,&alloc,&offset,"%s/?user=%s&password=%s",
			CONFIG_HISTORY_STORAGE_URL, CONFIG_CLICKHOUSE_USERNAME, CONFIG_CLICKHOUSE_PASSWORD);
	} else {
		data->url = zbx_strdup(NULL, CONFIG_HISTORY_STORAGE_URL);
	}

	zbx_rtrim(data->url, "/");
	data->buf = NULL;
	hist->value_type = value_type;
	hist->data = data;
	hist->destroy = clickhouse_destroy;
	hist->add_values = clickhouse_add_values;
	hist->flush = clickhouse_flush;
	hist->get_values = clickhouse_get_values;
	hist->agg_values = clickhouse_get_agg_values;
	hist->preload_values = clickhouse_preload_values;
	hist->requires_trends = 0;

	//preloading support
	//we only load data of the type value_type
	//of string, text and double types

	return SUCCEED;
}

#else

int	zbx_history_clickhouse_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	ZBX_UNUSED(hist);
	ZBX_UNUSED(value_type);

	*error = zbx_strdup(*error, "cURL library support >= 7.28.0 is required for clickhousesearch history backend");
	return FAIL;
}

#endif
