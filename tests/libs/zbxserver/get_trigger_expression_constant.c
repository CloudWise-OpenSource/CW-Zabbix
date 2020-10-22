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

#include "zbxserver.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;
	zbx_uint64_t		index = 0;
	const char		*expected_result = NULL, *expression = NULL;
	char			*actual_result = NULL;
	zbx_token_reference_t	token;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("expression", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expression)))
	{
		fail_msg("Cannot get input 'expression' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("index", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_uint64(param_handle, &index)))
	{
		fail_msg("Cannot get input 'index' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_result)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	token.index = index;
	get_trigger_expression_constant(expression, &token, &actual_result);

	zbx_mock_assert_str_eq("Invalid result", expected_result, actual_result);

	zbx_free(actual_result);
}
