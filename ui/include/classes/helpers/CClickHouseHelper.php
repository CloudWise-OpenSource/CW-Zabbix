<?php

/*
 * A helper class for working with ClickHouse database
 */

class CClickHouseHelper
{

    private static $self = null;
    private static $url = null;

    private static $oCurlResource = array();

    /**
     *
     * @return CClickHouseHelper
     */
    static private function instance()
    {
        if (self::$self == null) {
            self::$self = new self;
        }

        return self::$self;
    }

    /**Å
     * CClickHouseHelper constructor
     */
    public function __construct()
    {
        global $HISTORY;

        if (isset($HISTORY['username']) && isset($HISTORY['password'])) {
            $url = $HISTORY['url'] . '?user=' . $HISTORY['username'] . '&password=' . $HISTORY['password'];
        } else {
            $url = $HISTORY['url'];
        }

        self::$url = $url;
    }

    public function __destruct()
    {
        self::closeCurlResource();
    }

    /**
     * @param $url
     *
     * @return mixed
     */
    private static function getCurlResource($url = null)
    {
        $sResourceUrl = !is_null($url) ? $url : self::$url;

        if (in_array($sResourceUrl, self::$oCurlResource)) {
            return self::$oCurlResource[$sResourceUrl];
        }

        $oResource = curl_init();

        curl_setopt($oResource, CURLOPT_URL, $sResourceUrl);
        curl_setopt($oResource, CURLOPT_FORBID_REUSE, false);

        // receive server response ...
        curl_setopt($oResource, CURLOPT_RETURNTRANSFER, true);

        self::$oCurlResource[$sResourceUrl] = $oResource;

        return $oResource;
    }

    /**
     * @param $url
     *
     * @return bool
     */
    private static function closeCurlResource($url = null)
    {
        $oResource = self::getCurlResource($url);

        curl_close($oResource);

        return true;
    }

    /**
     * Perform request(s) to Elasticsearch and parse the results.
     *
     * @param mixed $request data to be sent
     *
     * @param       $is_table_result
     * @param       $columns
     *
     * @return array parsed result
     * @internal param string $method HTTP method to be used to perform request
     * @internal param string $endpoint requested url
     */
    public static function query($request, $is_table_result, $columns)
    {
        CClickHouseHelper::instance();

        $ch = self::getCurlResource();

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

        $server_output = curl_exec($ch);

        return self::parseResult($server_output, $is_table_result, $columns);

    }

    /**
     * Parse result and return two dimentional array of the result
     *
     * @param string $data result as a string
     *
     * @return array    parsed result  two dimentional array of the result
     */
    private static function parseResult($data, $is_table_result, $columns)
    {

        //to make processing simpler, lets distinguish results of two types - table (two dimensional array as result
        //returned or SINGLE, when one value (a number, probably)
        if ($is_table_result) {
            $result = [];

            $lines   = explode("\n", $data);
            $curline = 0;

            foreach ($lines as $line) {
                if (strlen(str_replace("\n", '', $line)) > 0) {
//			error("Processing line '$line'");
//			error("Columns count is ".count($columns)." field count is ".count(explode("\t",$line)));
                    $result[$curline] = array_combine($columns, explode("\t", $line));
                    $curline++;

                } else {
//			error("Got empty line, skipping");
                }
            }

        } else {
            //single result is here, stripping tabs,spaces and newlines
            $result = str_replace(array("\r", "\n", "\t"), '', $data);
        }

        /*	    ob_start();
                var_dump($result);
                $dresult = ob_get_clean();
                error("Dump of the result is '$dresult'");
        */

        return $result;
    }


}



