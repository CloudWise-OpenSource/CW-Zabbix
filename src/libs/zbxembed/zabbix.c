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
** but WITHOUT ANY WARRANTY; without even the envied warranty of
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
#include "zbxembed.h"
#include "embed.h"
#include "duktape.h"
#include "zabbix.h"

#define ZBX_ES_LOG_MEMORY_LIMIT	(ZBX_MEBIBYTE * 8)

/******************************************************************************
 *                                                                            *
 * Function: es_zabbix_dtor                                              *
 *                                                                            *
 * Purpose: Curlzabbix destructor                                        *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_dtor(duk_context *ctx)
{
	ZBX_UNUSED(ctx);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: es_zabbix_ctor                                              *
 *                                                                            *
 * Purpose: Curlzabbix constructor                                       *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_ctor(duk_context *ctx)
{
	if (!duk_is_constructor_call(ctx))
		return DUK_RET_TYPE_ERROR;

	duk_push_this(ctx);

	duk_push_c_function(ctx, es_zabbix_dtor, 1);
	duk_set_finalizer(ctx, -2);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: es_zabbix_status                                                 *
 *                                                                            *
 * Purpose: Curlzabbix.Status method                                          *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_zabbix_log(duk_context *ctx)
{
	zbx_es_env_t		*env;
	char			*message = NULL;
	int			level, err_index = -1;
	duk_memory_functions	out_funcs;

	level = duk_to_int(ctx, 0);

	if (SUCCEED != zbx_cesu8_to_utf8(duk_to_string(ctx, 1), &message))
	{
		message = zbx_strdup(message, duk_to_string(ctx, 1));
		zbx_replace_invalid_utf8(message);
	}

	zabbix_log(level, "%s", message);

	duk_get_memory_functions(ctx, &out_funcs);
	env = (zbx_es_env_t *)out_funcs.udata;

	if (NULL == env->json)
		goto out;

	if (ZBX_ES_LOG_MEMORY_LIMIT < env->json->buffer_size)	/* approximate limit */
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "log exceeds the maximum size of "
				ZBX_FS_UI64 " bytes.", ZBX_ES_LOG_MEMORY_LIMIT);
		goto out;
	}

	zbx_json_addobject(env->json, NULL);
	zbx_json_adduint64(env->json, "level", (zbx_uint64_t)level);
	zbx_json_adduint64(env->json, "ms", zbx_get_duration_ms(&env->start_time));
	zbx_json_addstring(env->json, "message", message, ZBX_JSON_TYPE_STRING);
	zbx_json_close(env->json);
out:
	zbx_free(message);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 0;
}

static const duk_function_list_entry	zabbix_methods[] = {
	{"Log",			es_zabbix_log,		2},
	{NULL, NULL, 0}
};

static int	es_zabbix_create_object(duk_context *ctx)
{
	duk_push_c_function(ctx, es_zabbix_ctor, 0);
	duk_push_object(ctx);

	duk_put_function_list(ctx, -1, zabbix_methods);

	if (1 != duk_put_prop_string(ctx, -2, "prototype"))
		return FAIL;

	duk_new(ctx, 0);
	duk_put_global_string(ctx, "Zabbix");

	return SUCCEED;
}

int	zbx_es_init_zabbix(zbx_es_t *es, char **error)
{
	if (0 != setjmp(es->env->loc))
	{
		*error = zbx_strdup(*error, es->env->error);
		return FAIL;
	}

	if (FAIL == es_zabbix_create_object(es->env->ctx))
	{
		*error = zbx_strdup(*error, duk_safe_to_string(es->env->ctx, -1));
		duk_pop(es->env->ctx);
		return FAIL;
	}

	return SUCCEED;
}
