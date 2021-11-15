# 项目名称

&emsp;&emsp;CW-Zabbix是一个Zabbix的二次开发项目，它目前基于Zabbix 5.0.4版本，使得Zabbix支持向Clickhouse写入指标数据。  

## 背景知识

&emsp;&emsp;Zabbix-Clickhouse项目编译出的应用支持将Zabbix数据导入到Clickhouse列数据库中。Zabbix在运行中产生的主要数据基本上可以分为history数据和trend数据。其中，trend数据是根据history计算出的各种中间值，如平均值，最高值等。而history就是zabbix搜集到的各种指标的实时历史数据，我们的目标就是把这些时序数据导入到clickhouse中。  
&emsp;&emsp;需要配置zabbix server启动所需的配置文件来实现上面的目标。  
&emsp;&emsp;在配置之前需要预先启动Clickhouse并导入表结构。启动Clickhouse时请注意配置中设置的http服务监听的端口号，下面的配置中会用到。

## 安装

&emsp;&emsp;说明：本项目源于官方5.0.4版本开发，在安装时只有zabbix **server**和**web**的配置文件比官方版本多了一些配置，并在clickhouse中创建相关表结构即可。具体配置详见**参数/配置**小节。

## 分支说明

- master - 主分支

- develop - 开发分支

### 环境依赖

**Zabbix Server 编译依赖**

&emsp;&emsp;unixODBC-devel、mariadb-devel、mariadb-server、net-snmp-devel、libxml2-devel、libcurl-devel、libevent-devel、autoconf、gcc、automake  

**Zabbix Web 运行依赖**

| 软件       | 版本              | 备注          |
| ---------- | ----------------- | ------------- |
| MySQL      | 推荐5.7.* 或以上  |               |
| Clickhouse | 推荐20.12.*或以上 |               |
| Apache     | 推荐2.4.* 或以上  |               |
| Nginx      | 推荐1.6.* 或以上  | 推荐使用nginx |
| PHP        | 必须7.2.* 或以上  |               |

| PHP扩展   | 版本          | 备注     |
| --------- | ------------- | -------- |
| gd        | 2.0 or later  |          |
| bcmath    |               |          |
| libXML    | 2.6.15 或以上 |          |
| xmlreader |               |          |
| xmlwriter |               |          |
| session   |               |          |
| sockets   |               |          |
| mbstring  |               |          |
| gettext   |               |          |
| ldap      |               |          |
| ibm_db2   |               |          |
| mysqli    |               |          |
| oci8      |               |          |
| pgsql     |               |          |
| opcache   |               | 选择安装 |
| yac       |               | 选择安装 |

### 编译/安装

Zabbix编译安装参考：**此版本支持数据源为mysql+clickhouse**，编译时请注意参数！官方文档供参考：

[Zabbix源代码安装]: https://www.zabbix.com/documentation/5.0/zh/manual/installation/install

```shell
# 这里仅展示安装zabbix-server-mysql，若安装zabbix-agent请参考上面链接
$ yum install unixODBC-devel mariadb-devel mariadb-server net-snmp-devel libxml2-devel libcurl-devel libevent-devel autoconf gcc automake  
$ autoreconf --install  
$ autoconf  
$ ./configure --enable-server --enable-agent --with-mysql --enable-ipv6 --with-net-snmp --with-libcurl --with-libxml2  
$ make install
```

**如果使用软件包方式，会将Zabbix Web运行环境一并安装。但使用本仓库代码搭建Zabbix Web，需要手动安装运行环境。**  

[Zabbix Web界面编译安装参考]: https://www.zabbix.com/documentation/5.0/zh/manual/installation/install#%E5%AE%89%E8%A3%85_zabbix_web_%E7%95%8C%E9%9D%A2

- 启动PHP 7.2、Mysql 5.7、Apache或Nginx

- 创建用户账户：

  ```shell
  $ groupadd zabbix
  $ useradd -g zabbix zabbix
  ```

- 创建 ZABBIX 数据库：

  ```shell
  $ mysql> create database zabbix character set utf8 collate utf8_bin;
  $ mysql> grant all privileges on zabbix.* to zabbix@localhost identified by 'password';
  $ mysql> quit;
  ```

- 在clickhouse中，执行以下语句：

  ```sql
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
  ```

- 如果需要在Clickhouse中创建trends与trends_unit视图，请执行以下语句：

  - 并在Zabbix Web配置文件中（zabbix.conf.php）取消禁用：（请完成Zabbix Web 部署后添加）
  - $HISTORY['disable_trends']=0;

  ```sql
  CREATE MATERIALIZED VIEW zabbix.trends
  ENGINE = AggregatingMergeTree() PARTITION BY toYYYYMM(clock) ORDER BY (clock, itemid)
  AS SELECT
   toStartOfHour(clock) AS clock,
   itemid,
   count(value_dbl) AS num,
   min(value_dbl) AS value_min,
   avg(value_dbl) AS value_avg,
   max(value_dbl) AS value_max
  FROM zabbix.history GROUP BY clock,itemid;
  
  CREATE MATERIALIZED VIEW zabbix.trends_uint
  ENGINE = AggregatingMergeTree() PARTITION BY toYYYYMM(clock) ORDER > BY (clock, itemid)
  AS SELECT
   toStartOfHour(clock) AS clock,
   itemid,
   count(value) AS num,
   min(value) AS value_min,
   avg(value) AS value_avg,
   max(value) AS value_max
  FROM zabbix.history GROUP BY clock,itemid;
  ```

- 在mysql中执行以下语句：

  ```shell
  shell> mysql -uroot -p<password>
  mysql> create database zabbix character set utf8 collate utf8_bin;
  mysql> create user 'zabbix'@'localhost' identified by '<password>';
  mysql> grant all privileges on zabbix.* to 'zabbix'@'localhost';
  mysql> quit;
  ```

- 把当前仓库中的UI目录通过Apache或Nginx进行代理，推荐使用Nginx

  ```shell
  $ mkdir <htdocs>/zabbix
  $ cd CW-Zabbix/UI
  $ cp -a . <htdocs>/zabbix
  ```

- 创建目录/etc/zabbix/

- 将仓库中/conf/目录下的zabbix_server.conf，复制到/etc/zabbix/，修改相关配置（详见“***参数/配置***”）

- 将仓库中/ui/conf/zabbix.conf.php.example，复制到/etc/zabbix/，删除“.example”后缀名，修改相关配置（详见“***参数/配置***”）

- 配置完成后，访问web页面，初始化zabbix，初始化请参考：

  [Zabbix Web界面初始化]: https://www.zabbix.com/documentation/5.0/zh/manual/installation/frontend

###  参数/配置

- zabbix_server.conf  (zabbix-server配置文件) 除了下面四项配置，其他配置与官方一致：

  ```c
  HistoryStorageURL=http://localhost:8123 // 注意，由于使用的是Clickhouse的rest接口，需要制定Clickhouse监听的http端口
  HistoryStorageTypes=uint,dbl,str,log,text //使用默认配置即可
  HistoryStorageName=clickhouse // 指明History存储方式
  HistoryStorageDBName=zabbix // 指示Clickhouse中所用的数据库名称
  ```
  
- zabbix.conf.php (Zabbix-web配置文件)

  文件中除了默认配置外，添加$HISTORY配置（链接clickhouse相关）

  ```php
  // Zabbix GUI configuration file.
  // ...省略与官方使用一致的配置项，以下展示对接clickhouse配置项
  
  $HISTORY['storagetype']='clickhouse'; // 指明History存储方式
  $HISTORY['url']='http://localhost:8123';  // Clickhouse接口
  $HISTORY['dbname']='zabbix'; // Clickhouse数据库名称
  $HISTORY['types'] = ['uint', 'text', 'str', 'dbl']; // 默认配置即可
  $ClickHouseDisableNanoseconds=0; // 支持纳秒存储，不需要禁用
  $HISTORY['disable_trends']=1; // 是否禁用trends记录，Clickhouse中创建trends与trends_unit视图，需要设置为0开启
  $YAC_CACHE['enable']=1; // 1：开启，0：关闭。Zabbix-API开启yac缓存，数据将以分钟片段进行缓存。按需配置
  ```

##  使用

- 完成以上步骤后，启动编译的zabbix-server即可

## 最新更新日志

###  v1.0

- 对接Clickhouse数据源
- 增加yac缓存，增加并发请求能力




