# 初版说明

CW-Zabbix是一个Zabbix的二次开发项目，它目前基于Zabbix 5.0.4版本，使得Zabbix支持向Clickhouse写入指标数据。

通过对接，Zabbix-Clickhouse项目编译出的应用支持将Zabbix数据导入到Clickhouse列数据库中。

Zabbix在运行中产生的主要数据基本上可以分为history数据和trend数据。其中，trend数据是根据history计算出的各种中间值，如平均值，最高值等。而history就是zabbix搜集到的各种指标的实时历史数据，我们的目标就是把这些时序数据导入到clickhouse中。

需要配置zabbix server启动所需的配置文件来实现上面的目标。

在配置之前需要预先启动Clickhouse并导入表结构。启动Clickhouse时请注意配置中设置的http服务监听的端口号，下面的配置中会用到。

表结构导入用到的sql语句：

'''
CREATE DATABASE zabbix;

CREATE TABLE zabbix.history ( day Date, 
 itemid UInt64, 
 clock DateTime, 
 ns UInt32, 
 value Int64, 
 value_dbl Float64, 
 value_str String 
 ) ENGINE = MergeTree(day, (itemid, clock), 8192);
CREATE TABLE zabbix.history_buffer (day Date, 
 itemid UInt64, 
 clock DateTime, 
 ns UInt32, 
 value Int64, 
 value_dbl Float64, 
 value_str String ) ENGINE = Buffer(zabbix, history, 8, 30, 60, 9000, 60000, 256000, 256000000) ;
'''

配置时的关键项目如下：

1.HistoryStorageURL

这个配置用以指示Clickhouse所在的服务器及监听的端口，配置示例：HistoryStorageURL=http://localhost:8123

注意，由于使用的是Clickhouse的rest接口，需要制定Clickhouse监听的http端口

2.HistoryStorageTypes

使用默认配置即可：HistoryStorageTypes=uint,dbl,str,log,text

3.HistoryStorageName

配置需要指明存储方式：HistoryStorageName=clickhouse

4.HistoryStorageDBName

指示Clickhouse中所用的数据库名称：HistoryStorageDBName=zabbix

配置好以上四项目后启动zabbix server即可。
