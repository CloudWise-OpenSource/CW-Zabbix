/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "zbxdb.h"
#include "dbcache.h"
#include "log.h"
#include "history_compress.h"

#define ZBX_TS_SEGMENT_BY	"itemid"
#define ZBX_TS_UNIX_NOW		"zbx_ts_unix_now"
#define ZBX_TS_UNIX_NOW_CREATE	"create or replace function "ZBX_TS_UNIX_NOW"() returns integer language sql" \
				" stable as $$ select extract(epoch from now())::integer $$"
#define COMPRESSION_TOLERANCE	(SEC_PER_HOUR * 2)

typedef enum
{
	ZBX_COMPRESS_TABLE_HISTORY = 0,
	ZBX_COMPRESS_TABLE_TRENDS
} zbx_compress_table_t;

typedef struct
{
	const char		*name;
	zbx_compress_table_t	type;
} zbx_history_table_compression_options_t;

static zbx_history_table_compression_options_t	compression_tables[] = {
	{"history",		ZBX_COMPRESS_TABLE_HISTORY},
	{"history_uint",	ZBX_COMPRESS_TABLE_HISTORY},
	{"history_str",		ZBX_COMPRESS_TABLE_HISTORY},
	{"history_text",	ZBX_COMPRESS_TABLE_HISTORY},
	{"history_log",		ZBX_COMPRESS_TABLE_HISTORY},
	{"trends",		ZBX_COMPRESS_TABLE_TRENDS},
	{"trends_uint",		ZBX_COMPRESS_TABLE_TRENDS}
};

static unsigned char	compression_status_cache = 0;
static int		compress_older_cache = 0;

/******************************************************************************
 *                                                                            *
 * Function: hk_check_table_segmentation                                      *
 *                                                                            *
 * Purpose: check that hypertables are segmented by itemid                    *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *             type       - [IN] history or trends                            *
 *                                                                            *
 ******************************************************************************/
static void	hk_check_table_segmentation(const char *table_name, zbx_compress_table_t type)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	/* get hypertable segmentby attribute name */
	result = DBselect("select c.attname from _timescaledb_catalog.hypertable_compression c"
			" inner join _timescaledb_catalog.hypertable h on (h.id = c.hypertable_id)"
			" where c.segmentby_column_index<>0 and h.table_name='%s'", table_name);

	for (i = 0; NULL != (row = DBfetch(result)); i++)
	{
		if (0 != strcmp(row[0], ZBX_TS_SEGMENT_BY))
			i++;
	}

	if (1 != i)
	{
		DBexecute("alter table %s set (timescaledb.compress, timescaledb.compress_segmentby = '%s',"
				" timescaledb.compress_orderby = '%s')", table_name, ZBX_TS_SEGMENT_BY,
				(ZBX_COMPRESS_TABLE_HISTORY == type) ? "clock,ns" : "clock");
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_get_table_compression_age                                     *
 *                                                                            *
 * Purpose: returns data compression age configured for hypertable            *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *                                                                            *
 * Return value: data compression age in seconds                              *
 *                                                                            *
 ******************************************************************************/
static int	hk_get_table_compression_age(const char *table_name)
{
	int		age = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	result = DBselect("select (p.older_than).integer_interval"
			" from _timescaledb_config.bgw_policy_compress_chunks p"
			" inner join _timescaledb_catalog.hypertable h on (h.id = p.hypertable_id)"
			" where h.table_name='%s'", table_name);

	if (NULL != (row = DBfetch(result)))
		age = atoi(row[0]);

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() age: %d", __func__, age);

	return age;
}

/******************************************************************************
 *                                                                            *
 * Function: hk_check_table_compression_age                                   *
 *                                                                            *
 * Purpose: ensures that table compression is configured to specified age     *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *             age        - [IN] compression age                              *
 *                                                                            *
 ******************************************************************************/
static void	hk_check_table_compression_age(const char *table_name, int age)
{
	int	obsolescence_threshold;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s age %d", __func__, table_name, age);

	if (age != (obsolescence_threshold = hk_get_table_compression_age(table_name)))
	{
		DB_RESULT	res;

		if (0 != obsolescence_threshold)
			DBfree_result(DBselect("select remove_compress_chunks_policy('%s')", table_name));

		zabbix_log(LOG_LEVEL_DEBUG, "adding compression policy to table: %s age %d", table_name, age);

		if (NULL == (res = DBselect("select add_compress_chunks_policy('%s', integer '%d')", table_name, age)))
			zabbix_log(LOG_LEVEL_ERR, "failed to add compression policy to table '%s'", table_name);

		DBfree_result(res);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_enable_compression                                    *
 *                                                                            *
 * Purpose: turns table compression on for items older than specified age     *
 *                                                                            *
 * Parameters: age - [IN] compression age                                     *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_enable_compression(int age)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < (int)ARRSIZE(compression_tables); i++)
	{
		DBfree_result(DBselect("select set_integer_now_func('%s', '"ZBX_TS_UNIX_NOW"', true)",
				compression_tables[i].name));
		hk_check_table_segmentation(compression_tables[i].name, compression_tables[i].type);
		hk_check_table_compression_age(compression_tables[i].name, age);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_disable_compression                                   *
 *                                                                            *
 * Purpose: turns table compression off                                       *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_disable_compression(void)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < (int)ARRSIZE(compression_tables); i++)
	{
		if (0 == hk_get_table_compression_age(compression_tables[i].name))
			continue;

		DBfree_result(DBselect("select remove_compress_chunks_policy('%s')", compression_tables[i].name));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_compression_init                                      *
 *                                                                            *
 * Purpose: initializing compression for history/trends tables                *
 *                                                                            *
 ******************************************************************************/
void	hk_history_compression_init(void)
{
	int		disable_compression = 0;
	zbx_config_t	cfg;
	DB_RESULT	result;
	DB_ROW		row;
	char		*db_log_level = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DBconnect(ZBX_DB_CONNECT_NORMAL);
	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_DB_EXTENSION);
	compression_status_cache = cfg.db.history_compression_status;
	compress_older_cache = cfg.db.history_compress_older;

	if (ON == cfg.db.history_compression_availability)
	{
		/* surpress notice logs during DB initialization */
		result = DBselect("show client_min_messages");

		if (NULL != (row = DBfetch(result)))
		{
			db_log_level = zbx_strdup(db_log_level, row[0]);
			DBexecute("set client_min_messages to warning");
		}
		DBfree_result(result);

		if (ON == cfg.db.history_compression_status)
		{
			if (0 == cfg.db.history_compress_older)
			{
				disable_compression = 1;
				hk_history_disable_compression();
			}
			else
			{
				DBexecute(ZBX_TS_UNIX_NOW_CREATE);
				hk_history_enable_compression(cfg.db.history_compress_older + COMPRESSION_TOLERANCE);
			}
		}
		else
		{
			hk_history_disable_compression();
		}
	}
	else if (ON == cfg.db.history_compression_status)
	{
		disable_compression = 1;
	}

	if (0 != disable_compression && ZBX_DB_OK > DBexecute("update config set compression_status=0"))
		zabbix_log(LOG_LEVEL_ERR, "failed to set database compression status");

	zbx_config_clean(&cfg);

	if (NULL != db_log_level)
	{
		DBexecute("set client_min_messages to %s", db_log_level);
		zbx_free(db_log_level);
	}

	DBclose();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_compression_update                                    *
 *                                                                            *
 * Purpose: history compression settings periodic update                      *
 *                                                                            *
 * Parameters: cfg - [IN] database extension history compression settings     *
 *                                                                            *
 ******************************************************************************/
void	hk_history_compression_update(zbx_config_db_t *cfg)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (OFF == cfg->history_compression_availability)
		goto out;

	if (ON == cfg->history_compression_status)
	{
		if (cfg->history_compression_status != compression_status_cache ||
				cfg->history_compress_older != compress_older_cache)
		{
			DBexecute(ZBX_TS_UNIX_NOW_CREATE);
			hk_history_enable_compression(cfg->history_compress_older + COMPRESSION_TOLERANCE);
		}
	}
	else if (cfg->history_compression_status != compression_status_cache)
	{
		hk_history_disable_compression();
	}

	compression_status_cache = cfg->history_compression_status;
	compress_older_cache = cfg->history_compress_older;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
