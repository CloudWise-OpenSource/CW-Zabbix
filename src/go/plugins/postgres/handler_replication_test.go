// +build postgres_tests

/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package postgres

import (
	"fmt"
	"testing"
)

func TestPlugin_replicationHandler(t *testing.T) {
	// create pool or aquare conn from old pool
	sharedPool, err := getConnPool(t)
	if err != nil {
		t.Fatal(err)
	}

	type args struct {
		conn *postgresConn
		key  string
	}
	tests := []struct {
		name    string
		p       *Plugin
		args    args
		wantErr bool
	}{
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.count"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.count"},
			false,
		},
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.status"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.status"},
			false,
		},
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.lag.sec"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.lag.sec"},
			false,
		},
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.lag.b"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.lag.b"},
			false,
		},
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.recovery_role"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.recovery_role"},
			false,
		},
		{
			fmt.Sprintf("replicationHandler should return ptr to Pool for replication.master.discovery.application_name"),
			&impl,
			args{conn: sharedPool, key: "pgsql.replication.master.discovery.application_name"},
			false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := tt.p.replicationHandler(tt.args.conn, tt.args.key, []string{})
			if (err != nil) != tt.wantErr {
				t.Errorf("Plugin.replicationHandler() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if tt.wantErr == false {
				if tt.args.key == "pgsql.replication.status" || tt.args.key == "pgsql.replication.master.discovery.application_name" {
					if len(got.(string)) == 0 && err != errorCannotParseData {
						t.Errorf("Plugin.replicationTransactions() at DeepEqual error = %v, wantErr %v", err, tt.wantErr)
						return
					}
				}
			}
		})
	}
}
