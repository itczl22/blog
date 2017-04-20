<?php
require_once 'Base/RedisTools.php' ;
require_once 'SubNews/FormatNews.php' ;
require_once 'SubNews/ZTRelateNews.php' ;
require_once 'Base/ClientFactory.php' ;
require_once 'News/QQNewsData.php' ;
require_once '/usr/local/zk_agent/names/nameapi.php' ;
require_once "Base/des.php";
require_once "News/QQUserInfo.php";

define('REDIS_KEY_PRE', 'SUB.INEWS.');
define('MC_EXPIRE_TIME', '1800');
define ('ID_MOD_FOR_MYSQL', 100000 ) ; 
define('MAX_INDEX_IDS', 500) ; 
define('MC_INDEX_PRE', 'QQNEWS.INDEX.');
define('MC_CONTENT_PRE', 'QQNEWS.');
define('MC_MEDIA_INFO_PRE', 'SUB.MEDIA.INFO.');
define('MC_MEDIA_TYPE_PRE', 'SUB.MEDIA.TYPE.');
define('MC_CHANNEL_MEDIA_INFO_PRE', 'SUB.CHANNEL.MEDIA.INFO.');
define('MC_MEDIA_UIN_PRE', 'SUB.MEDIA.UIN.');
define('MC_NEWS_MEDIA_PRE', 'SUB.NEWS.MEDIA.INFO.');
define('MC_CARD_INFO_PRE', 'SUB.CARD.INFO.');
define("APC_CHLID_INFO_PRE", 'APC.CHLID.INFO.');
define('APC_TOPIC_EXPIRE_TIME', '1200');
define('APC_SUB_CHLID_INFO_TIME', '300');
define('APC_INDEX_TIMEOUT', '300');
define('HOT_VALUE_STEP', 0.1);
define('HOT_VALUE_COUNT', 3);
define('HOT_VALUE_FREQUENT', 2);
define('HOT_VALUE_HOT_CHLID_RATE', 0.9);
define('SEX_HOT_CHLID_RATE', 1.2);
define('HOT_VALUE_NOTHOT_CHLID_RATE', 1.2);
define('TAG_SWITCH', 1);
define('MC_EXPIRE_TIME_SUB_LIKE', '86400');
define ('MAX_INDEX_ORDER', 4294967295);

class SubNews_GetNewsTool {
    protected static $_errStr = '' ;
    protected $_mysql_conn = null ;
    protected $_mc = null ;
    protected $_apc_pre = 'sub_qqnews_cnt';
    protected $_apc_timeout = 60;
    private static $_instance = null;

    protected function __construct() {
        //DEGRADE_LEVEL=2
        if($_REQUEST['kb_degrade'] < 2) {
            $this->_mc = ClientFactory::getMemcacheClientByName( 'COMMENT' ) ;
        }
    }

    public function __destruct() {
    }

    public function getError() {
        return self::$_errStr ;
    }

    public static function getInstance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function sortIndex($indexes, $forShort=false) {
        $newsIndex = array();
        foreach (range(1, MAX_INDEX_IDS) as $i) {
            $tmp = null;
            $flag = null;
            foreach ($indexes as $key => $index) {
                if (count($index) <= 0) {
                    continue;
                }
                if ($tmp === null || $tmp['order'] > $index[0]['order']) {
                    $tmp = $index[0];
                    $flag = $key;
                }
                if (count($indexes[$flag]) <= 0) {
                    unset($indexes[$flag]);
                }
            }
            if ($tmp !== null) {
                array_shift($indexes[$flag]);
                unset( $tmp['cid'] );
                $tmp['groupId'] = $tmp['chlid'] . '-' . $tmp['order'];
                //unset( $tmp['order'] );
                $newsIndex[] = $tmp;
                //ios控制在50篇文章
                if($forShort === true && count($newsIndex) >= 50)
                {
                    return $newsIndex;
                }
            }
            if (count($indexes) <= 0) {
                break;
            }
        }
        return $newsIndex;
    }

    /**
     * 按媒体分组展示订阅文章
     */
    private function groupSortIndex($indexes) {
        $newsIndex = array();
        $fixGroupId = 88888;
        foreach (range(1, MAX_INDEX_IDS) as $i) {
            $tmp = null;
            $flag = null;
            foreach ($indexes as $key => $index) {
                if (count($index) <= 0) {
                    continue;
                }

                //微信文章索引里没有order，得计算一下
                $tmp['order'] = isset($tmp['order']) ? $tmp['order'] : MAX_INDEX_ORDER - $tmp['timestamp'];
                $index[0]['order'] = isset($index[0]['order']) ? $index[0]['order'] : MAX_INDEX_ORDER - $index[0]['timestamp'];

                if ($tmp === null || $tmp['order'] > $index[0]['order']) {
                    $tmp = $index[0];
                    $flag = $key;
                }
                if (count($indexes[$flag]) <= 0) {
                    unset($indexes[$flag]);
                }
            }
            if ($tmp !== null) {
                //取头3篇文章聚成组
                $tmpGroupNews = array();
                foreach(range(1,3) as $i)
                {
                    $tmpIndex = array_shift($indexes[$flag]);
                    if($tmpIndex == null) break;
                    $tmpGroupNews[] = $tmpIndex;
                }
                foreach($tmpGroupNews as $one)
                {
                    unset( $one['cid'] );
                    if(!empty($one['chlid'])) {
                        $one['groupId'] = strval($one['chlid']);
                        $one['type'] = '0';
                    } else if(!empty($one['tagid'])){
                        $one['groupId'] = strval($one['tagid']);
                        $one['type'] = '1';
                    } else {
                        $one['groupId'] = strval($fixGroupId);
                        $one['type'] = '0';
                    }
                    $newsIndex[] = $one;
                }
                ++$fixGroupId;
                //后面的文章忽略
                unset($indexes[$flag]);

            }
            if (count($indexes) <= 0) {
                break;
            }
        }
        return $newsIndex;
    }


    private function setNewsIndexToApc($index) {
        foreach ($index as $key => $value) {
            if (is_numeric($key)) {
                $key = 'MC_INDEX_PRE' . $key;
            }
            Logs::info(__FUNCTION__ . ": set apc, key = " . $key);
            apc_store($key, $value, APC_INDEX_TIMEOUT);
        }
    }

    public function getNewsIndex($chlids, $lastid=0, $count=20, $groupMethod=false) {
        $chlids = array_slice($chlids, 0, 100);
        $news = array();
        $noApcChlids = array();
        $noCacheChlids = array();
        $index = array();
        foreach ($chlids as $apcChlid) {
            $cnt = apc_fetch(MC_INDEX_PRE . $apcChlid);
            if ($cnt != false) {
                $index[MC_INDEX_PRE . $apcChlid] = $cnt;
            } else {
                $noApcChlids[] = $apcChlid;
            }
        }
        if (count($noApcChlids) > 0) {
            $mcIndex = $this->getNewsIndexFromCache($noApcChlids, $noCacheChlids);
            if (!is_array($mcIndex) || count($noCacheChlids) > 0) {
                foreach ($noCacheChlids as $chlid) {
                    $tmpIndex = $this->getNewsIndexFromDB($chlid);

                    //debug by ryanhe
                    /*
                       if (is_array($tmpIndex) && $tmpIndex !== false) {
                       $this->setNewsIndexToMc($chlid, $tmpIndex);
                       Logs::info(__FUNCTION__ . ": save index to mc, chlid = " . $chlid);
                       }
                     */
                    $mcIndex[MC_INDEX_PRE . $chlid] = $tmpIndex;
                }
            }
            $this->setNewsIndexToApc($mcIndex);
            if (is_array($mcIndex) && count($mcIndex) > 0) {
                $index = array_merge($index, $mcIndex);
            }
        }
        if($groupMethod == false)
        {
            $index = $this->sortIndex($index);
        }
        else
        {
            $index = $this->groupSortIndex($index);
        }
        $ids = $this->getIdsFromIndex($index, $lastid, $count);

        /*
           require_once "News/QQSC.php";
           $r = QQSC::getInstance()->getArticleCommentNums( $cids );
           Logs::info( "qqsc,nums:" . print_r( $r, true ) );
           foreach ($ids as &$id )
           {
           unset( $id['cid'] );
           unset( $id['order'] );
           }
         */
        return $ids;
    }

    //获取文章列表，并按照用户阅读习惯调整文章顺序
    public function getNewsIndexIndividual($chlids, $devid, $qq, $lastid=0, $count=20, $processHotChlid = false) {
        $news = array();
        $noCacheChlids = array();
        $index = $this->getNewsIndexFromCache($chlids, $noCacheChlids);
        if (!is_array($index) || count($noCacheChlids) > 0) {
            foreach ($noCacheChlids as $chlid) {
                $tmpIndex = $this->getNewsIndexFromDB($chlid);

                $index[] = $tmpIndex;
            }
        }

        //获取用户的兴趣媒体和分类排名
        /*
        $userSex = -1;
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_GENERAL' ) ;

        require_once('/usr/local/zk_agent/names/nameapi.php');
        $host = new ZkHost;
        getHostByKey('i.match.qq.com' , $host);
        $url = 'http://' . $host->ip . ':' . $host->port;

        if($qq != 0)
        {
            Logs::info(__FUNCTION__ . ": get user perfer by qq=" . $qq);

            if($mc != null)
            {
                $k = 'sub.like.' . $qq;
                $data = $mc->get($k);
            }

            if(empty($data))
            {
                $url = $url . "/app/mediaforqq?qq=" . $qq;
                Logs::info(__FUNCTION__ . ":curl url=" . $url);
                $ret = ClientFactory::curl_file_get_contents($url, 1);
                $ret = json_decode($ret, true);
                if($ret['code'] == 0 && !empty($ret['data']))
                {
                    $data = $ret['data'];
                    if($mc != null && !empty($data))
                    {
                        $mc->set($k, $data, MC_EXPIRE_TIME_SUB_LIKE);
                    }
                }
            }
            else
            {
                Logs::info(__FUNCTION__ . ': get user interest from mc');
            }

            //有qq的情况下，获取用户的性别
            $qq_info = QQUserInfo::getInstance()->GetUinsNickName($qq, array($qq));
            Logs::info(__FUNCTION__ . ": qq info=" . print_r($qq_info, true));
            $qq_info = $qq_info[$qq];
            //男为1，女为0
            $userSex = isset($qq_info['sex']) ? intval(!$qq_info['sex']) : -1;

            //获取用户订阅列表
            $userSubList = SubNews_UserSubTool::getInstance()->getUserSubList($qq, false);
            $subChlids = array();
            foreach($userSubList as $chlid)
            {
                $subChlids[$chlid] = 1;
            }
        }
        else
        {
            Logs::info(__FUNCTION__ . ": get user perfer by devid=" . $devid);

            if($mc != null)
            {
                $k = 'sub.like.' . $devid;
                $data = $mc->get($k);
            }

            if(empty($data))
            {
                $url = $url . "/mediarank?utn=" . $devid;
                Logs::info(__FUNCTION__ . ":curl url=" . $url);
                $ret = ClientFactory::curl_file_get_contents($url, 1);
                $ret = json_decode($ret, true);
                if(!empty($ret) && $ret['ret'] == 0)
                {
                    $data = $ret;
                    if($mc != null && !empty($data))
                    {
                        $mc->set($k, $data, MC_EXPIRE_TIME_SUB_LIKE);
                    }
                }
            }
            else
            {
                Logs::info(__FUNCTION__ . ': get user interest from mc');
            }
        }
        Logs::info(__FUNCTION__ . ":cat interest=".print_r($data, true));

        $man_not_like_cat = array(9, 10, 13);
        $woman_not_like_cat = array(3, 4, 6);

        if(!empty($data))
        {
            //兴趣分类提高权值
            foreach($data['t'] as $key => $cat)
            {
                $catCount[$cat] = 0;
                $catRank[$cat] = $key;
            }

            $dicKey = "hotswitch.sub.appnews.com.dic";
            $dicValue = '';
            getValueByKey($dicKey , $dicValue); 
            Logs::info(__FUNCTION__ . ": tagswitch=$dicValue");
            $tagMethod = $dicValue == '1' ? 1 : 0;

            //按tag获取兴趣
            if($tagMethod == 1)
            {
                //常看频道获取tag
                $chlidToTags = $this->getTagsFromChlidMulti($data['m']);

                $dynamicValue = 0.8;
                if(!empty($chlidToTags))
                {
                    foreach($data['m'] as $chlid)
                    {
                        //tag打分逻辑
                        $chlid_id = intval($chlid);
                        if(isset($chlidToTags[$chlid_id]) && $chlidToTags[$chlid_id] != 0)
                        {
                            if(!isset($tagValues[$chlidToTags[$chlid_id]]))
                            {
                                $tagValues[$chlidToTags[$chlid_id]] = $dynamicValue;
                                if($dynamicValue < 0.9)
                                {
                                    $dynamicValue += 0.015;
                                }
                            }
                            else
                            {
                                if($tagValues[$chlidToTags[$chlid_id]] > 0.75)
                                {
                                    $tagValues[$chlidToTags[$chlid_id]] -= 0.02;
                                }
                                else if($tagValues[$chlidToTags[$chlid_id]] > 0.70)
                                {
                                    $tagValues[$chlidToTags[$chlid_id]] -= 0.01;
                                }
                            }
                        }
                    }
                    Logs::info(__FUNCTION__ . ": tag values=" . print_r($tagValues, true));
                }
            }

            // iPhone hot chlids
            $hotChlids = array(
                    45 => 1,
                    55 => 1,
                    63 => 1,
                    1055 => 1,
                    66 => 1,
                    67 => 1,
                    70 => 1,
                    80 => 1,
                    92 => 1,
                    1058 => 1,
                    1052 => 1,
                    1032 => 1,
                    1050 => 1,
                    1034 => 1,
                    1035 => 1,
                    1061 => 1,
                    1062 => 1,
                    1157 => 1,
                    1158 => 1,
                    1169 => 1,
                    1171 => 1,
                    1135 => 1,
                    1154 => 1,
                    1149 => 1,
                    1152 => 1,
                    1145 => 1,
                    1106 => 1,
                    1227 => 1,
                    1221 => 1,
                    1251 => 1,
                    1252 => 1,
                    1245 => 1,
                    1261 => 1,
                    1242 => 1,
                    1349 => 1,
                    1229 => 1,
                    1367 => 1,
                    1341 => 1,
                    1371 => 1,
                    1405 => 1,
                    1397 => 1,
                    1236 => 1,
                    1238 => 1,
                    1365 => 1,
                    1373 => 1,
                    1480 => 1,
                    1504 => 1,
                    1222 => 1,
                    1316 => 1,
                    1344 => 1,
                    1250 => 1,
                    1339 => 1,
                    1401 => 1,
                    1478 => 1,
                    1487 => 1);

            $notHotChlids = array(
                    1708 => 1,
                    1503 => 1,
                    1502 => 1,
                    1435 => 1,
                    1436 => 1,
                    1407 => 1,
                    1333 => 1,
                    1431 => 1,
                    1430 => 1,
                    1286 => 1,
                    1475 => 1,
                    1565 => 1,
                    1240 => 1,
                    1477 => 1,
                    1237 => 1,
                    1505 => 1
                    );

            $seed = 1;
            $defaultSeed = count($catCount) * HOT_VALUE_STEP + HOT_VALUE_STEP * HOT_VALUE_COUNT + 1 + HOT_VALUE_STEP;

            foreach($index as &$newsIndex)
            {
                foreach($newsIndex as $key => &$item)
                {
                    if ($key === 0) {
                        $item['order'] = 0;
                    }

                    $valuePref = 1;
                    //兴趣方式切换开关
                    if($tagMethod == 0)
                    {
                        //按对分类的感兴趣程度来调整文章排名
                        if(isset($catCount[$item['contentType']]) && $catCount[$item['contentType']] < HOT_VALUE_COUNT)
                        {
                            $catCount[$item['contentType']]++;
                            $valuePref = $seed + HOT_VALUE_STEP * $catCount[$item['contentType']] + HOT_VALUE_STEP * $catRank[$item['contentType']];
                        }
                        else
                        {
                            $valuePref = $defaultSeed;
                        }
                    }
                    else
                    {
                        //按对tag的兴趣来调整文章排名
                        if(!empty($tagValues) && isset($item['originChlid']))
                        {
                            $tag = $chlidToTags[$item['originChlid']];
                            if(isset($tagValues[$tag]))
                            {
                                $valuePref = $tagValues[$tag];
                            }
                        }

                    }

                    //降低已订阅媒体的排名
                    $valueNotSub = 1;
                    if(isset($subChlids[$item['originChlid']]))
                    {
                        $valueNotSub = HOT_VALUE_FREQUENT;
                    }

                    //iphone用户微调
                    if($processHotChlid)
                    {
                        //降低一部分媒体的排名
                        if(isset($notHotChlids[intval($item['originChlid'])]))
                        {
                            $valuePref = $valuePref * HOT_VALUE_NOTHOT_CHLID_RATE;
                        }

                    }

                    //男性用户降低情感、生活、时尚的排名
                    if($userSex == 1)
                    {
                        if(in_array($item['contentType'], $man_not_like_cat))
                        {
                            $valuePref = $valuePref * SEX_HOT_CHLID_RATE;
                            //Logs::info(__FUNCTION__ . ": man not like, id=" . $item['id']);
                        }
                    }
                    //女性用户降低体育、科技、视觉的排名
                    else if ($userSex == 0)
                    {
                        if(in_array($item['contentType'], $woman_not_like_cat))
                        {
                            $valuePref = $valuePref * SEX_HOT_CHLID_RATE;
                            //Logs::info(__FUNCTION__ . ": woman not like, id=" . $item['id']);
                        }
                    }

                    $item['order'] = intval($item['order']) * $valuePref * $valueNotSub;
                    //Logs::info($item['id'] . '|' . $item['order'] . '|' . $valuePref . '|' . $valueNotSub . '|' . $item['originChlid']);
                }
            }
        } else {
            Logs::info("get boss data failed $devid");
        }
        */

        $index = $this->sortIndex($index, $processHotChlid);
        $ids = $this->getIdsFromIndex($index, $lastid, $count);

        return $ids;
    }

    //带有微信文章的我的订阅索引获取，真TM复杂啊。。
    public function getMySubNewsIndexWithWx($chlids) 
    {
        $chlids = array_slice($chlids, 0, 100);

        //把微信的挑出来
        $wx_chlids = array();
        $om_chlids = array();
        foreach($chlids as $item) {
            if(empty($item) || !is_numeric($item)) {
                continue;
            }
            $chlidType = ClientFactory::jundgeChlid($item);
            if($chlidType === 'wx') {
                $wx_chlids[] = $item;
            } else if($chlidType === 'sub'){
                $om_chlids[] = $item;
            }
        }
        $news = array();
        $noApcChlids = array();
        $noCacheChlids = array();
        $index = array();
        foreach ($om_chlids as $apcChlid) {
            $cnt = apc_fetch(MC_INDEX_PRE . $apcChlid);
            if ($cnt != false) {
                $index[MC_INDEX_PRE . $apcChlid] = $cnt;
            } else {
                $noApcChlids[] = $apcChlid;
            }
        }

        if(!empty($wx_chlids)) {
            //微信媒体的文章索引是按openid获取的，需要转一下
            $handler = SubNews_WxMediaInfoTool::getInstance();
            $wx_openids = $handler->getWxOpenIdByOmChlid($wx_chlids);

            //微信的也从apc读一下
            foreach ($wx_openids as $apcChlid) {
                $cnt = apc_fetch(MC_INDEX_PRE . 'wx.' . $apcChlid);
                if ($cnt != false) {
                    $index[MC_INDEX_PRE . $apcChlid] = $cnt;
                } else {
                    //这里实际上是把openid记录进来了
                    $noApcChlids[] = $apcChlid;
                }
            }
        }

        if (count($noApcChlids) > 0) {
            $mcIndex = $this->getNewsIndexFromCache($noApcChlids, $noCacheChlids);
            if (!is_array($mcIndex) || count($noCacheChlids) > 0) {
                foreach ($noCacheChlids as $chlid) {
                    //订阅的
                    if(is_numeric($chlid)) {
                        $tmpIndex = $this->getNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . $chlid] = $tmpIndex;
                    } else {
                        //微信的是openid，非数字
                        $tmpIndex = $this->getWxNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . 'wx.' . $chlid] = $tmpIndex;

                        if (is_array($tmpIndex) && $tmpIndex !== false) {
                            $this->setWxNewsIndexToMc($chlid, $tmpIndex);
                        }
                    }
                }
            }
            $this->setNewsIndexToApc($mcIndex);
            if (is_array($mcIndex) && count($mcIndex) > 0) {
                $index = array_merge($index, $mcIndex);
            }
        }

        //TODO:调整一下，加入wx的索引之后怎么处理
        $index = $this->groupSortIndex($index);

        return $index;
    }

    public function getMergeTagSubNewsIndexWithWx($chlids, $tagids)
    {    
        $chlids = array_slice($chlids, 0, 100);
        $tagids = array_slice($tagids, 0, 100);

        //把微信的挑出来
        $wx_chlids = array();
        $om_chlids = array();
        foreach($chlids as $item) {
            if(empty($item) || !is_numeric($item)) {
                continue;
            }
            $chlidType = ClientFactory::jundgeChlid($item);
            if($chlidType === 'wx') {
                $wx_chlids[] = $item;
            } else if($chlidType === 'sub'){
                $om_chlids[] = $item;
            }
        }
        $news = array();
        $noApcChlids = array();
        $noCacheChlids = array();
        $index = array();
        foreach ($om_chlids as $apcChlid) {
            $cnt = apc_fetch(MC_INDEX_PRE . $apcChlid);
            if ($cnt != false) {
                $index[MC_INDEX_PRE . $apcChlid] = $cnt;
            } else {
                $noApcChlids[] = $apcChlid;
            }
        }

        if(!empty($wx_chlids)) {
            //微信媒体的文章索引是按openid获取的，需要转一下
            $handler = SubNews_WxMediaInfoTool::getInstance();
            $wx_openids = $handler->getWxOpenIdByOmChlid($wx_chlids);

            //微信的也从apc读一下
            foreach ($wx_openids as $apcChlid) {
                $cnt = apc_fetch(MC_INDEX_PRE . 'wx.' . $apcChlid);
                if ($cnt != false) {
                    $index[MC_INDEX_PRE . $apcChlid] = $cnt;
                } else {
                    //这里实际上是把openid记录进来了
                    $noApcChlids[] = $apcChlid;
                }
            }
        }

        if (count($noApcChlids) > 0) {
            $mcIndex = $this->getNewsIndexFromCache($noApcChlids, $noCacheChlids);
            if (!is_array($mcIndex) || count($noCacheChlids) > 0) {
                foreach ($noCacheChlids as $chlid) {
                    //订阅的
                    if(is_numeric($chlid)) {
                        $tmpIndex = $this->getNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . $chlid] = $tmpIndex;
                    } else {
                        //微信的是openid，非数字
                        $tmpIndex = $this->getWxNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . 'wx.' . $chlid] = $tmpIndex;

                        if (is_array($tmpIndex) && $tmpIndex !== false) {
                            $this->setWxNewsIndexToMc($chlid, $tmpIndex);
                        }
                    }
                }
            }
            $this->setNewsIndexToApc($mcIndex);
            if (is_array($mcIndex) && count($mcIndex) > 0) {
                $index = array_merge($index, $mcIndex);
            }
        }
        //////////////////////////////////////////////////////
        $noCacheTagids = array();
        //$tagindex = $this->getTagsNewsFromTagIndex($tagids, 3);
        $tagindex = $this->getTagsThreeNewsFromCache($tagids, $noCacheTagids,3);
        if (!is_array($tagindex) || count($noCacheTagids) > 0) {
            foreach ($noCacheTagids as $tagid) {
                $tmptagIndex = $this->getTagNewsIndexFromDB($tagid,3);

                if (is_array($tmptagIndex) && $tmptagIndex !== false) {
                    $this->setTagsThreeNewsToMc($tagid, $tmptagIndex);
                    Logs::info(__FUNCTION__ . ": save index to mc, tagid = " . $tagid);
                }

                $tagindex[] = $tmptagIndex;
            }
        }
        $tagNewsIndex = array(); 
        foreach($tagindex as $key=>$newsitem){
            if($newsitem){
                $tagNewsIndex[$key] = array_slice($newsitem,0,3);
            }
        }
        $index = array_merge($index, $tagNewsIndex);

        $index = $this->groupSortIndex($index);
        $newsMap = array();
        foreach($index as $k=>&$newslist){
            if(isset($newsMap[$newslist['id']])){
                Logs::info(__FUNCTION__ . "this id is repeat ".$newslist['id']);
                unset($index[$k]);
                continue;
            }
            $newsMap[$newslist['id']] = 1;
        }
        $index = array_values($index);
        return $index;

    }

    public function getBigTagSubNewsIndexWithWx($chlids, $tagsinfo,$QaNewids=array())
    {    
        
        $QaList = array_slice($QaNewids, 0, 100);
        $QaListNum = count($QaList);
        $QaReqNum = 100;
        
        $chlids = array_slice($chlids, 0, 100);
        $tagsinfo = array_slice($tagsinfo, 0, 100, true);
        $countNum = count($chlids);
        $indexNum = 100;
        $tagsNum = count($tagsinfo);
        $reqNum = 100;
        if($countNum == 1){
            $indexNum = 100;
        }else if($countNum == 2){
            $indexNum = 60;
        }else if($countNum < 5){
            $indexNum = 50;
        }else if($countNum < 10){
            $indexNum = 30;
        }else if($countNum < 20){
            $indexNum = 20;
        }else if($countNum > 30){
            $indexNum = 10;
        }

        if($tagsNum  == 1){
            $reqNum = 40; 
        }else if($tagsNum == 2){
            $reqNum = 40;
        }else if($tagsNum < 5){
            $reqNum = 20;
        }else if($tagsNum < 10){
            $reqNum = 20;
        }else{
            $reqNum = 10;
        }
        
        Logs::info(__FUNCTION__ . "chlid {$countNum} tagnum {$tagsNum} index num is {$indexNum} chlid".$reqNum." qalistNum:{$QaListNum} index:{$QaReqNum}");

        //把微信的挑出来
        $wx_chlids = array();
        $om_chlids = array();
        foreach($chlids as $item) {
            if(empty($item) || !is_numeric($item)) {
                continue;
            }
            $chlidType = ClientFactory::jundgeChlid($item);
            if($chlidType === 'wx') {
                $wx_chlids[] = $item;
            } else if($chlidType === 'sub'){
                $om_chlids[] = $item;
            }
        }
        $noApcChlids = array();
        $noCacheChlids = array();
        $index = array();
        foreach ($om_chlids as $apcChlid) {
            $cnt = apc_fetch(MC_INDEX_PRE . $apcChlid);
            if ($cnt != false) {
                $index[MC_INDEX_PRE . $apcChlid] = $cnt;
            } else {
                $noApcChlids[] = $apcChlid;
            }
        }

        if(!empty($wx_chlids)) {
            //微信媒体的文章索引是按openid获取的，需要转一下
            $handler = SubNews_WxMediaInfoTool::getInstance();
            $wx_openids = $handler->getWxOpenIdByOmChlid($wx_chlids);

            //微信的也从apc读一下
            foreach ($wx_openids as $apcChlid) {
                $cnt = apc_fetch(MC_INDEX_PRE . 'wx.' . $apcChlid);
                if ($cnt != false) {
                    $index[MC_INDEX_PRE . $apcChlid] = $cnt;
                } else {
                    //这里实际上是把openid记录进来了
                    $noApcChlids[] = $apcChlid;
                }
            }
        }

        if (count($noApcChlids) > 0) {
            $mcIndex = $this->getNewsIndexFromCache($noApcChlids, $noCacheChlids);
            if (!is_array($mcIndex) || count($noCacheChlids) > 0) {
                foreach ($noCacheChlids as $chlid) {
                    //订阅的
                    if(is_numeric($chlid)) {
                        $tmpIndex = $this->getNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . $chlid] = $tmpIndex;
                    } else {
                        //微信的是openid，非数字
                        $tmpIndex = $this->getWxNewsIndexFromDB($chlid);
                        $mcIndex[MC_INDEX_PRE . 'wx.' . $chlid] = $tmpIndex;

                        if (is_array($tmpIndex) && $tmpIndex !== false) {
                            $this->setWxNewsIndexToMc($chlid, $tmpIndex);
                        }
                    }
                }
            }
            $this->setNewsIndexToApc($mcIndex);
            if (is_array($mcIndex) && count($mcIndex) > 0) {
                $index = array_merge($index, $mcIndex);
            }
        }

        //Logs::info("index is ".print_r($index, true));
        require_once("VideoNews/VideoNewsTool.php");
        $vnt = VideoNews_VideoNewsTool::getInstance() ;
        $video_index = $vnt->getVideoNewsIndex($om_chlids,false);
        $len = strlen(MC_INDEX_PRE);
        $newsVideoIndex = array();
        foreach($index as $omkey=>$item){
            $item = array_slice($item,0,50);
            $videokey = 'QQNEWS.VIDEO.INDEX.'.substr($omkey, $len);
            if(!empty($video_index[$videokey]) && is_array($video_index[$videokey])){
                $tmp = array_merge($item, $video_index[$videokey]);
                $tmp = $this->sortMergeIndex($tmp);
                $newsVideoIndex[$omkey] = $tmp;
            }else{
                $newsVideoIndex[$omkey] = $item;
            }
        }

        //Logs::info("newsVideoIndex  is ".print_r($newsVideoIndex,true));
        $index = $newsVideoIndex;
        
        //////////////////////////////////////////////////////
        $noCacheTagids = array();
        $tagresult = array();
        $tagindex = $this->getTagsNewsFromSearchAndCache($tagsinfo, $reqNum);
        foreach($tagindex as $key=>&$newsitem){
            $tmp = array_slice($newsitem, 0, $reqNum);

            if(is_array($tmp) && !empty($tmp)){
                $tagresult = array_merge($tagresult,$tmp);
            }
        }

        require_once "SubNews/GetSimilarIndex.php";
        $filterSimIds = array();
        $similarHandler = SubNews_GetSimilarIndex::getInstance();
        $similarHandler->filterSimilarIndex($tagresult, $filterIds);
        $tagresult = array_values($tagresult);
        Logs::info("tag index is:".count($tagresult));
        
        require_once "question/UserSubSort.php";
        return UserSubSort::getInstance()->getUserSortList($index,$indexNum,$QaList,$QaReqNum,$tagresult);

        $tagindex = array();
        $tagTotal = array();
        $mediaTotal = array();
        $TagmergeIndex = array();
        $TagmergeIndex2 = array();
        $MediamergeIndex = array();
        $MediamergeIndex2 = array();
        $remainIndex = array();
        $totalIndex = array();
        $lasttime = time() - 172800;
        $mediatime = time() - 172800;
        if($index){
            foreach($index as $item){
                $item = array_slice($item, 0, $indexNum);
                if(is_array($item) && !empty($item)){
                    $tmp = array();
                    $tmp2 = array();
                    foreach($item as $k=>$list){
                        if($list['timestamp'] > $mediatime && count($tmp) < 3){
                            $tmp[] = $list;
                            unset($item[$k]);
                            continue;
                        }
                        if($list['timestamp'] > $mediatime && count($tmp2) < 3){
                            $tmp2[] = $list;
                            unset($item[$k]);
                            continue;
                        }
                    }
                    if(!empty($tmp)){
                        $MediamergeIndex[] = $tmp;
                    }
                    if(!empty($tmp2)){
                        $MediamergeIndex2[] = $tmp2;
                    }
                    $remainIndex = array_merge($remainIndex, $item);
                }
            }
        }
        $MediamergeIndex = $this->mediaSortIndex($MediamergeIndex);
        Logs::info("om&wx in 48 hour is ".print_r($MediamergeIndex, true));
        $MediamergeIndex2 = $this->mediaSortIndex($MediamergeIndex2);
        Logs::info("second om&wx in 48 hour is ".print_r($MediamergeIndex2, true));
        $MediamergeIndex = array_merge($MediamergeIndex, $MediamergeIndex2);

        if($tagresult){
            foreach($tagresult as $item){
                $tagindex[$item['tagid']][] = $item;
            }
            foreach($tagindex as $key=>$item){
                if(is_array($item) && !empty($item)){
                    $tmp = array();
                    $tmp2 = array();
                    foreach($item as $k=>$list){
                        if($list['timestamp'] > $lasttime && count($tmp) < 3){
                            $tmp[] = $list;
                            unset($item[$k]);
                            continue;
                        }
                        if($list['timestamp'] > $lasttime && count($tmp2) < 3){
                            $tmp2[] = $list;
                            unset($item[$k]);
                            continue;
                        }
                    }
                    if(!empty($tmp)){
                        $TagmergeIndex = array_merge($TagmergeIndex, $tmp);
                    }
                    if(!empty($tmp2)){
                        $TagmergeIndex2 = array_merge($TagmergeIndex2, $tmp2);
                    }
                    $remainIndex = array_merge($remainIndex, $item);
                }
            }
            $TagmergeIndex = $this->sortMergeIndex($TagmergeIndex);
            Logs::info("tag in 24 hour is ".print_r($TagmergeIndex, true));
            $TagmergeIndex2 = $this->sortMergeIndex($TagmergeIndex2);
            Logs::info("sencond tag in 24 hour is ".print_r($TagmergeIndex2, true));
            $TagmergeIndex = array_merge($TagmergeIndex, $TagmergeIndex2);
            foreach($MediamergeIndex as $k=>$media){
                if(is_array($media) && !empty($media)){
                    $tagtmp = array_splice($TagmergeIndex, 0, 3);
                    if($k == 0){
                        if($media[0]['timestamp'] > $tagtmp[0]['timestamp']){
                            $totalIndex = array_merge($totalIndex, $media);
                            $totalIndex = array_merge($totalIndex, $tagtmp);
                        }else{
                            $totalIndex = array_merge($totalIndex, $tagtmp);
                            $totalIndex = array_merge($totalIndex, $media);
                        }
                    }else{
                        $totalIndex = array_merge($totalIndex, $tagtmp);
                        $totalIndex = array_merge($totalIndex, $media);
                    }
                }
            }
			
            $remainIndex = $this->sortMergeIndex($remainIndex);
            if(is_array($TagmergeIndex) && !empty($TagmergeIndex)){
                $remainIndex = array_merge($TagmergeIndex, $remainIndex);
            }
            $totalIndex = array_merge($totalIndex, $remainIndex);
        }else{
            foreach($MediamergeIndex as $key=>$item){
                if(is_array($item) && !empty($item)){
                    $totalIndex = array_merge($totalIndex,$item);
                }
            }
            $remainIndex = $this->sortMergeIndex($remainIndex);          
            $totalIndex = array_merge($totalIndex, $remainIndex);
        }

        Logs::info("total index is:".count($totalIndex));
        $newsMap = array();
        foreach($totalIndex as $k=>$newslist){
            if(isset($newsMap[$newslist['id']])){
                Logs::info(__FUNCTION__ . "this id is repeat ".$newslist['id']);
                unset($totalIndex[$k]);
                continue;
            }
            $newsMap[$newslist['id']] = 1;
        }

        $totalIndex = array_slice($totalIndex, 0, 100);
        $totalIndex = array_values($totalIndex);

        return $totalIndex;
    }

    private function sortMergeIndex($indexs){
        $newsindex = array();
        $num = count($indexs) + 1;
        foreach(range(1, $num) as $i){
            $tmp = null;
            $flag = null;
            foreach($indexs as $key=>$item){
                if($tmp === null || $tmp['timestamp'] < $item['timestamp']){
                    $tmp['timestamp'] = $item['timestamp'];
                    $flag = $key;
                }
            }
            if($tmp !== null){
                $newsindex[] = $indexs[$flag]; 
                unset($indexs[$flag]);
            }
            if(count($indexs) <= 0 || count($newsindex) >= 200){
                break;
            }
        }
        return $newsindex;
    }   

    /**
     * 按媒体展示最新订阅文章排序
     */
    private function mediaSortIndex($indexes) {
        $newsIndex = array();
        foreach (range(1, MAX_INDEX_IDS) as $i) {
            $tmp = null;
            $flag = null;
            foreach ($indexes as $key => $index) {
                if (count($index) <= 0) {
                    continue;
                }
                if ($tmp === null || $tmp['timestamp'] < $index[0]['timestamp']) {
                    $tmp = $index[0];
                    $flag = $key;
                }
                if (count($indexes[$flag]) <= 0) {
                    unset($indexes[$flag]);
                }
            }
            if ($tmp !== null) {
                $newsIndex[] = $indexes[$flag];
                unset($indexes[$flag]);
            }
            if (count($indexes) <= 0) {
                break;
            }
        }
        return $newsIndex;
    }

    // 通过MC获取媒体的tag信息
    private function getTagsFromChlidMulti($chlids)
    {
        if($this->_mc == null)
        {
            Logs::info(__FUNCTION__ . ": mc is null");
            return array();
        }
        else
        {
            foreach ($chlids as $chlid)
            {
                $keys[$chlid] = "SUB.MEDIA.TAG." . $chlid;
            }
            if (count($keys) > 20) { 
                Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
                ItilLogs::ItilErrLog(__CLASS__,
                        0,      
                        0,      
                        ItilLogs::LVL_ERROR,
                        1,      
                        __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
            }   
            $tagDatas = $this->_mc->get_multi($keys);
            $chlidToTags = array();
            if( !empty($tagDatas))
            {
                Logs::info(__FUNCTION__ . ": get from mc");
                foreach ($keys as $chlid => $key) 
                {
                    if (isset($tagDatas[$key]) === false) 
                    {
                        Logs::info(__FUNCTION__ . ": no chlid tag in mc. key = {$key}");
                        continue;
                    }
                    //TODO: 后面tag可以是多个的情况需要另外处理
                    $chlidTotags[$chlid] = intval($tagDatas[$key]);
                }
            }
            else
            {
                Logs::info(__FUNCTION__ . ": chlid tags data is empty in mc");
            }
        }

        return $chlidTotags;
    }
    //复用hotIds
    public function getNews($ids, $itemFunction = 'formatListItem', $chlid = 0, $apptype = 'qqnews', $appver = 300, $hotIds=array(), $enableGroup=false, $is_coll = 0, $opts=array()) {
        Logs::info(__FUNCTION__ . print_r($ids, true));
        if (count($ids) <= 0) {
            return array();
        }

        if( $opts['noapc'] == 1 )
        {
            Logs::info( "noapc gets." );
            $news_cache = $this->getNewsContentFromCache($ids, false);
        }
        else
        {
            $news_cache = $this->getNewsContentFromCache($ids, true);
        }

        $nocache_ids = array();
        $cache_ids = array();
        foreach ($news_cache as $n) {
            $cache_ids[substr($n['id'], 0, -2)] = 1;
        }

        foreach ($ids as $id) {
            if (!isset($cache_ids[substr($id, 0, -2)])) {
                $nocache_ids[] = $id;
            }
        }

        if ($nocache_ids != array()) {
            //wx文章不同的库
            $wx_nocache_ids = array();
            $video_nocache_ids = array();
            $other_nocache_ids = array();
            $weibo_nocache_ids = array();//微博文章
            foreach($nocache_ids as $id) {
                if ($id{0} === '1') {
                    $weibo_nocache_ids[] = $id;
                } elseif (strval(substr($id, 8, 1)) === 'B') {
                    $wx_nocache_ids[] = $id;
                } elseif (strval(substr($id, 8, 1)) === 'V') {
                    $video_nocache_ids[] = $id;
                } else {
                    $other_nocache_ids[] = $id;
                }
            }
            $news = array();
            if(!empty($other_nocache_ids)) {
                $cgi = $_SERVER['REQUEST_URI'];
                if ($pos = strpos($cgi, '?')) {
                    $cgi = substr($cgi, 0, $pos);
                }    
                $errMsg = __FUNCTION__ .": news miss in mc, cgi=" . $cgi . " ids=" . implode('|', $other_nocache_ids);
                Logs::info($errMsg);
                ItilLogs::ItilErrLog(__CLASS__, 0, 0, ItilLogs::LVL_ERROR, 2, $errMsg);

                $other_news = $this->getNewsContentFromDBNew($other_nocache_ids);

                if(is_array($other_news) && !empty($other_news)) {
                    $news = array_merge($news, $other_news);
                }
            }
            if(!empty($wx_nocache_ids)) {
                $not_exist_ids = array();
                $wx_news = $this->getWxNewsContentFromDB($wx_nocache_ids, $not_exist_ids);
                if(is_array($wx_news) && !empty($wx_news)) {
                    $news = array_merge($news, $wx_news);
                }

                if(!empty($not_exist_ids)) {
                    $this->setWxNotExistIdToMc($not_exist_ids);
                }
            }
            if(!empty($video_nocache_ids)) {
                $not_exist_ids = array();
                $videoHandler = VideoNews_GetVideoNewsDbTool::getInstance();
                $video_news = $videoHandler->getVideoNewsContentFromDB($video_nocache_ids, $not_exist_ids);
                if(is_array($video_news) && !empty($video_news)) {
                    $news = array_merge($news, $video_news);
                }
            }
            $this->setNewsContentToMc($news);
            
            if(!empty($weibo_nocache_ids)) {
                //$not_exist_ids = array();
                $om_weibo_news = $this->getOMWeiboContent($weibo_nocache_ids);
                if(is_array($om_weibo_news) && !empty($om_weibo_news)) {
                    $news = array_merge($news, $om_weibo_news);
                }
            }
        }
        if ($news) {
            if ($news_cache && $news_cache[0] != false) {
                $news = array_merge( $news_cache, $news); 
            }       
        } else {
            $news = $news_cache;
        }       
        if (!$news) {
            Logs::info("getNews: get news failed from db. chlid={$chlid}, ids = " . print_r($ids, true));
            return false;
        }

        // 是不是需要将kb_ext里的字段merge进来
        $mergeExt = TRUE;
        if(isset($opts['merge_ext']) && is_numeric($opts['merge_ext']) && $opts['merge_ext'] === 0)
        {
            $mergeExt = FALSE;
        }

        // 是否把图片替换成https
        require_once 'Base/ABTestTool.php';
        $picToHttps = ABTestTool::checkImageToHttps();
        if(isset($opts['picToHttps']) && is_numeric($opts['picToHttps']) && $opts['picToHttps'] === 0)
        {
            $picToHttps = 0;
        }
        elseif($_REQUEST['apptypeOnly'] == 'areading')
        {
            $picToHttps = 0;
        }

        Logs::info('mergeExt : ' . $mergeExt);
        // o(n^2)...
        $sortNews = array();
        $nameVal = '';
        getValueByKey('daily.nomerge', $nameVal);
        $noMergeArr = array();
        if($nameVal)
        {
            $noMergeArr = explode('|', $nameVal);
        }
        foreach($ids as $id)
        {
            foreach($news as $tmp)
            {
                if(substr($tmp['id'], 0, -2) === substr($id, 0, -2))
                {
                    $tmp['id'] = $id;
                    if($mergeExt && $id{0}!='1')
                    {
                        $content = unserialize($tmp['content']);
                        if(($content['article_from'] != '101') && isset($content['ext']) && is_array($content['ext']))
                        {
                            $keepImgExpType = FALSE;
                            //@bob，无图模式的还是要保留
                            if($content['ext']['action']['lsImgExpType'] == '4')
                            {
                                $keepImgExpType = TRUE;
                            }
                            // added by yctian @20160816 OM作者自定义封面图的文章也要保留
                            elseif($content['ext']['action']['lsImgExpType'] == '3' && $content['article_from'] == '2314' && $content['ext']['action']['Fimgurl500'])
                            {
                                $picArr = explode(',', $tmp['ext']['action']['Fimgurl500']);
                                if($picArr >= 3)
                                {
                                    $keepImgExpType = TRUE;
                                }
                            }
                            elseif($content['ext']['action']['lsImgExpType'] == '2' && $content['article_from'] == '2314' && $content['ext']['action']['Fimgurl5'])
                            {
                                $keepImgExpType = TRUE;
                            }
                            if(!$keepImgExpType)
                            {
                                unset($content['ext']['action']['lsImgExpType']);
                            }
                        }
                        if(in_array(substr($tmp['id'], 0, -2) . '00', $noMergeArr))
                        {
                            unset($content['kb_ext']['content']);
                            unset($content['kb_ext']['cnt_html']);
                            unset($content['kb_ext']['cnt_attr']);
                        }
                        if(isset($content['kb_ext']) && is_array($content['kb_ext']))
                        {
                            $content = $this->mergeKBExt($content, $tmp['id']);
                        }
                        $tmp['content'] = serialize($content);
                    }
                    
                    if($picToHttps)
                    {
                        $content = unserialize($tmp['content']);
                        $this->replaceNewsImageUrlToHttps($id, $content);
                        $tmp['content'] = serialize($content);
                    }


                    $sortNews[] = $tmp;
                    break;
                }
            }
        }

        if( $itemFunction === 'ztrelatenews' ) {
            ZTRelateNews::getZTRelateNewsList( $sortNews, $opts );
        } elseif('origcontent'===$itemFunction) {
            return $sortNews;
            
        } elseif('formatContent'===$itemFunction) {
            FormatNews::$itemFunction($sortNews, $apptype, $appver, $is_coll, $chlid, $opts['blackScroll']);
        } else {
            FormatNews::$itemFunction($sortNews, $apptype, $appver, $hotIds, $enableGroup, $is_coll, $chlid);
        }
        return $sortNews;
    }
    
    public function getOMWeiboContent($ids) 
    {
        if ($ids == null || !is_array($ids) || !$ids) 
        {
            Logs::info(__FUNCTION__ . ": ids is illegal, ids = " . print_r($ids, true));
            return array();
        }
        
        //apc缓存取下
        $newsList = array();
        $apcNoIds = array();
        foreach ($ids as $id)
        {
           $tmp = apc_fetch('WB_LOC:'.$id);
           if (!empty($tmp) && ($tmpJson=json_decode($tmp,true)))
           {
               $newsList[$id] = $tmpJson;
           }
           else
           {
               $apcNoIds[] = $id;
           }
        }
        
        if (empty($apcNoIds) && !empty($newsList))
        {
        	return  array_values($newsList);
        }
        
        $key = 'kb_interface.lan.online';
        $apiHost = new ZKHost();
        getHostByKey($key, $apiHost);
    
        $url = 'http://'.$apiHost->ip.':'.$apiHost->port.'/g/getWeibo';
        $params = array(
                'chl_from' => 'om_get',
                'weibo_id' => implode(',',$apcNoIds),
        );
        
        $ret = ClientFactory::curl_file_get_contents($url,0.3,'','',$params);
        if (strlen($ret)<=0)
        {
        	//重试下
            $ret = ClientFactory::curl_file_get_contents($url,0.3,'','',$params);
        }
        
        
        if(strlen($ret) > 0) 
        {
            $ret = json_decode($ret,true);
            if($ret['response']['code'] == 0) 
            {
                foreach($ret['data']['news_content'] as $item)
                {
                    if (empty($item)) continue;
                    $tmplist =  array('id'=>$item['data']['article_id'],'content'=>serialize($item['data']));
                    $newsList[$item['data']['article_id']] = $tmplist;
                    $jsonApc = json_encode($tmplist);
                    apc_store('WB_LOC:'.$id,$jsonApc,60);//1分钟
                }
            }
        }
        
        //保证顺序一致
        $retData = array();
        foreach ($ids as $val)
        {
            if (isset($newsList[$val]))
            {
                $retData[] = $newsList[$val];
            }
        }
        return $retData;
    }

    public static function replaceNewsImageUrlToHttps($id, &$content)
    {
        if(!$content)
        {
            return;
        }
        // 1stPic
        if(isset($content['1stPic']) && $content['1stPic'])
        {
            foreach($content['1stPic'] as &$rItem)
            {
                if(isset($rItem['imgurl']) && $rItem['imgurl'])
                {
                    CommonFunction::replaceImgUrlToHttps($rItem['imgurl']);
                }
            }
            unset($rItem);
        }
        // 各种列表图
        $fieldArr = array('imgurl', 'imgurl_small', 'qqnews_thu_big', 'qqnews_thu');
        foreach($fieldArr as $tField)
        {
            if(isset($content[$tField]) && $content[$tField])
            {
                CommonFunction::replaceImgUrlToHttps($content[$tField]);
            }
        }
        // ext action里面图片
        for($i = 0; $i <= 40; $i++)
        {
            if(isset($content['ext']['action']['Fimgurl' . $i]) && $content['ext']['action']['Fimgurl' . $i])
            {
                CommonFunction::replaceImgUrlToHttps($content['ext']['action']['Fimgurl' . $i]);
            }
        }
        if(isset($content['ext']['action']['Fimgurl500']) && $content['ext']['action']['Fimgurl500'])
        {
            CommonFunction::replaceImgUrlToHttps($content['ext']['action']['Fimgurl500']);
        }
        // qq音乐
        if($content['ext']['action']['qqmusic'])
        {
            foreach($content['ext']['action']['qqmusic'] as &$rItem)
            {
                if($rItem['albumpic'])
                {
                    CommonFunction::replaceImgUrlToHttps($rItem['albumpic']);
                }
            }
            unset($rItem);
        }
        // 电影
        if(isset($content['ext']['action']['relmovie']['cover_hpic']) && $content['ext']['action']['relmovie']['cover_hpic'])
        {
            CommonFunction::replaceImgUrlToHttps($content['ext']['action']['relmovie']['cover_hpic']);
        }

        // 正文
        if($content['content'])
        {
            foreach($content['content'] as &$rItem)
            {
                if($rItem['type'] == 'img_url')
                {
                    if($rItem['img_url'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['img_url']);
                    }
                    if($rItem['img_url_wifi'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['img_url_wifi']);
                    }
                    if(is_array($rItem['img']))
                    {
                        foreach($rItem['img'] as &$rItem2)
                        {
                            if($rItem2['imgurl'])
                            {
                                CommonFunction::replaceImgUrlToHttps($rItem2['imgurl']);
                            }
                        }
                        unset($rItem2);
                    }
                }
                elseif($rItem['type'] == 'video')
                {
                    if($rItem['img'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['img']);
                    }
                }
                unset($rItem);
            }
        }

        if($content['cnt_attr'])
        {
            foreach($content['cnt_attr'] as $key => &$rItem)
            {
                if(substr($key, 0, 4) == 'IMG_')
                {
                    if(is_array($rItem['img']))
                    {
                        foreach($rItem['img'] as &$rItem2)
                        {
                            if($rItem2['imgurl'])
                            {
                                CommonFunction::replaceImgUrlToHttps($rItem2['imgurl']);
                            }
                        }
                        unset($rItem2);
                    }
                }
                elseif(substr($key, 0, 6) == 'VIDEO_')
                {
                    if(is_array($rItem['img']))
                    {
                        foreach($rItem['img'] as &$rItem2)
                        {
                            if($rItem2['imgurl'])
                            {
                                CommonFunction::replaceImgUrlToHttps($rItem2['imgurl']);
                            }
                        }
                        unset($rItem2);
                    }
                    if($rItem['app_logo'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['app_logo']);
                    }
                    if($rItem['openApp']['icon'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['openApp']['icon']);
                    }
                }
                elseif(substr($key, 0, 4) == 'MAP_')
                {
                    if($rItem['pic'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['pic']);
                    }
                }
                elseif(substr($key, 0, 6) == 'WEIBO_')
                {
                    foreach($rItem as &$rItem2)
                    {
                        if($rItem2['headimg'])
                        {
                            CommonFunction::replaceImgUrlToHttps($rItem2['headimg']);
                        }
                        if($rItem2['imgurl'])
                        {
                            CommonFunction::replaceImgUrlToHttps($rItem2['imgurl']);
                        }
                    }
                    unset($rItem2);
                }
                elseif(substr($key, 0, 8) == 'COMMENT_')
                {
                    foreach($rItem as &$rItem2)
                    {
                        if($rItem2['head_url'])
                        {
                            CommonFunction::replaceImgUrlToHttps($rItem2['head_url']);
                        }
                    }
                    unset($rItem2);
                }
                elseif(substr($key, 0, 6) == 'VOICE_')
                {
                    if($rItem['head'])
                    {
                        CommonFunction::replaceImgUrlToHttps($rItem['head']);
                    }
                }

                // todo LINK GROUPPIC GIFT
            }
        }

        // todo rel_news
    }

    public static function mergeKBExt($content, $newsId = '')
    {
        if(isset($content['kb_ext']) && is_array($content['kb_ext']))
        {
            $kbExt = $content['kb_ext'];
            unset($content['kb_ext']);
            $aType = intval($content['atype']);
            if(in_array($aType, array(0,1)))
            {
                // 先把内容字段提取出来吧
                $origArr = array('content', 'cnt_html', 'cnt_attr');
                foreach($origArr as $t_field)
                {
                    if(isset($kbExt[$t_field]))
                    {
                        $content[$t_field] = $kbExt[$t_field];
                        unset($kbExt[$t_field]);
                        // merger content的文章单独记录一下
                        $GLOBALS['MERGE_CONTENT'][$newsId][] = $t_field;
                    }
                }
            }
            if(count($kbExt) > 0)
            {
                $content = self::array_merge_multi($content, $kbExt);
            }
        }

        return $content;
    }

    /**
     * 多维数组合并 参数和array_merge一样 2个参数以上 后面覆盖前面的
     * @return array
     */
    public static function array_merge_multi()
    {
        $args = func_get_args();
        if (!isset($args[0]) && !array_key_exists(0, $args))
        {
            return array();
        }

        $arr = array();
        foreach ($args as $key => $value)
        {
            if (is_array($value))
            {
                foreach ($value as $k => $v)
                {
                    if (is_array($v))
                    {
                        if (!isset($arr[ $k ]) && !array_key_exists($k, $arr))
                        {
                            $arr[ $k ] = array();
                        }
                        $arr[ $k ] = self::array_merge_multi($arr[ $k ], $v);
                    }
                    else
                    {
                        $arr[ $k ] = $v;
                    }
                }
            }
        }

        return $arr;
    }

    private function getSupportCountFromIds($keys) {
        $redis = ClientFactory::getRedisClient('NEWS_INFO');
        return $redis->gets($keys);
    }

    private function getNewsIndexFromCache($chlids, &$noCacheChlids=array()) {
        $chlids = array_slice($chlids, 0, 100);
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            $err = 'mc init failed!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        } else if (!is_array($chlids)|| count($chlids) <= 0) {
            $err = 'chlid is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        $keys = array();
        foreach ($chlids as $chlid) {
            if(is_numeric($chlid)) {
                $keys[$chlid] = MC_INDEX_PRE . $chlid;
            } else {
                //微信的是openid
                $keys[$chlid] = MC_INDEX_PRE . 'wx.' . $chlid;
            }
        }
        if (count($keys) > 20) { 
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0,      
                    0,      
                    ItilLogs::LVL_ERROR,
                    1,      
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
        }   
        $news_index = $mc->get_multi($keys);
        foreach ($keys as $chlid => $key) {
            if (isset($news_index[$key]) === false) {
                Logs::info(__FUNCTION__ . ": no index in mc. key = {$key}");
                $noCacheChlids[] = $chlid;
            }
        }
        if ($news_index == null || !is_array($news_index)) {
            Logs::info(__FUNCTION__ . ": mc return null, news_index = " . print_r($news_index, true));
            return array();
        }
        return $news_index;
    }

    private function setNewsIndexToMc($chlid, $index) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc init failed!");
            return false;
        }
        $key = MC_INDEX_PRE . $chlid;

        $mc->set($key, $index, MC_EXPIRE_TIME);
    }

    private function getIdsFromIndex($index, $lastid=0, $count=20) {
        return $index;
    }

    private function getNewsIndexFromDB($chlid) {
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;
        if( $this->_mysql_conn == null ) {
            $err = ClientFactory::getError() ; 
            Logs::info( "getNewsIndexFromDB: connect db faild, chlid={$chlid}, error={$err}" ) ;  
            return false ; // 数据库连接失败。 
        }

        $num = ClientFactory::getDbTableNum('QQNEWS_SUB_R', ($chlid % 100));
        $tblName = 'sub_news_index_' . $num ;

        $chlid = mysql_real_escape_string($chlid, $this->_mysql_conn);
        $sql = "select distinct `id`, `chlid`, `order`, `cid`, `timestamp` from {$tblName} where chlid='{$chlid}' order by `order` limit 200;";
        Logs::info($sql);
        $rs = mysql_query( $sql, $this->_mysql_conn ) ;
        if( !$rs ) {
            Logs::info( "getNewsIndexFromDB: mysql_query faild, chlid={$chlid}, error=" . mysql_error()  ) ;
            return false ; // 数据库执行失败。       
        }
        $rd = mysql_fetch_assoc($rs); 
        $index = array();

        while( $rd != false ) {
            //增加order字段用于排序
            $index[] = array('id' => $rd['id'], 'order'=> $rd['order'], 'cid'=>$rd['cid'], 'chlid'=>strval($rd['chlid']),'timestamp'=>$rd['timestamp']);
            $rd = mysql_fetch_assoc($rs);
        }       
        return $index;
    }

    private function getNewsContentFromCache($ids, $apc_enable = false) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
            return array();
        } else if ($ids == null || !is_array($ids) || !$ids) {
            Logs::info(__FUNCTION__ . ": ids is illegal, ids = " . print_r($ids, true));
            return array();
        }

        if ($apc_enable == true) {
            $no_apc_ids = array();
            $news_apc = array();
            foreach ($ids as $id) {
                $cnt = apc_fetch($this->_apc_pre . substr($id, 0, -2) . "00");
                if ($cnt != false) {
                    $news_apc[] = $cnt;
                    Logs::info(__FUNCTION__ . ": get {$id} content from apc");
                } else {
                    $no_apc_ids[] = $id;
                }
            }
            if (count($no_apc_ids) == 0) {
                Logs::info(__FUNCTION__ . ": return from apc");
                return $news_apc;
            } else {
                $ids = $no_apc_ids;
            }
        }

        $mc_ids = array();
        foreach ($ids as $id) {
            //$mc_ids[] = MC_CONTENT_PRE . substr($id, 0, -2) . "00";
            $mc_ids[] = MC_CONTENT_PRE . $id;
        }
        if (count($keys) > 20) { 
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0,      
                    0,      
                    ItilLogs::LVL_ERROR,
                    1,      
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
        }   
        $contents = $mc->get_multi($mc_ids);

        if ($contents == null || !is_array($contents) || $contents == array()) {
            Logs::info(__FUNCTION__ . ": contents in mc is empty, mc key = " . print_r($mc_ids, true));
            if ($apc_enable && is_array($news_apc) && count($news_apc) != 0) {
                Logs::info(__FUNCTION__ . ": return from apc");
                return $news_apc;
            } else {
                return array();
            }
        }

        $ret = array();
        foreach ($contents as $content) {
            $ret[] = $content;
            if ($apc_enable) {
                apc_store($this->_apc_pre . substr($content['id'], 0, -2) . "00", $content, $this->_apc_timeout);
                //Logs::info(__FUNCTION__ . ": save " . substr($content['id'], 0, -2) . "00" . " to apc");
            }
        }
        if ($apc_enable && is_array($news_apc) && count($news_apc) != 0) {
            Logs::info(__FUNCTION__ . ": return from apc and mc");
            $ret = array_merge($ret, $news_apc);
        } else {
            Logs::info(__FUNCTION__ . ": return from mc");
        }
        return $ret;
    }

    private function setNewsContentToMc($news) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
            return false;
        } else if ($news == null || !is_array($news) || !$news) {
            Logs::info(__FUNCTION__ . ": news is illegal, news = " . print_r($news, true));
            return false;
        }

        foreach ($news as $n) {
            $mc_key = MC_CONTENT_PRE . $n['id'];
            Logs::info(__FUNCTION__ . ": save {$mc_key} to mc");
            if (!$mc->set($mc_key, $n)) {
                Logs::info(__FUNCTION__ . ": save {$mc_key} to mc fail!!!");
            }
        }

        return true;
    }

    //把数据库里不存在的id写入到mc，避免透到db
    private function setWxNotExistIdToMc($not_exist_ids) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
            return false;
        }

        $emptyContent = serialize(array('is_deleted'=>1));
        foreach ($not_exist_ids as $id) {
            $mc_key = MC_CONTENT_PRE . $id;
            Logs::info(__FUNCTION__ . ": save not exist id {$mc_key} to mc");
            $value = array('id'=>$id, 'content'=>$emptyContent);
            if (!$mc->set($mc_key, $value, 259200)) {
                Logs::info(__FUNCTION__ . ": save not exist id {$mc_key} to mc fail!!!");
            }
        }

        return true;
    }

    private function getTableNameFromId($id) {
        static $table = 'sub_news_content_';
        $month = substr($id, 0, 6);
        $day = substr($id, 0, 8);
        if($day >= '20151213') {
            return $table . $day;
        } else {
            return $table . $month;
        }
    }

    //按文章id获取对应的数据库名
    protected function getSubDBNames($ids)
    {
        $value = '';
        getValueByKey('dbrouter.config.qqnews_sub.dic', $value);
        $value = explode('|', $value);
        $routerConfig = array();
        foreach($value as $item) {
            $itemArr = explode(',', $item);
            $routerConfig[] = array('date'=>$itemArr[0], 'dbConfig'=>$itemArr[1]);
        }
        //Logs::info("configs=" . print_r($routerConfig, true));

        $subDbNames = array();
        foreach($ids as $index=>$id) {
            if (strlen($id) < 8) {
                continue;
            }
            if (!ctype_alpha($id{8})) { //腾讯网的文章实际上是走了QQNewsData，放这里吧 
                $subDbNames['QQNEWS_SUB_R'][] = $id;
                continue;
            } 
            $configFound = false;
            foreach($routerConfig as $configItem) {
                if (substr($id, 0, 8) < $configItem['date']) {
                    $subDbNames[$configItem['dbConfig']][] = $id;
                    $configFound = true;
                    break;
                }
            }
            if(!$configFound) {
                $subDbNames['QQNEWS_SUB_R'][] = $id;
            }
        }
        //Logs::info("subDbNames=" . print_r($subDbNames, true));
        return $subDbNames;
    }

    public function getNewsContentFromDBNew($subIds) 
    {
        $subDbNames = $this->getSubDBNames($subIds);
        if(empty($subDbNames)) {
            return false;
        }
        $news = array();
        foreach($subDbNames as $dbName=>$ids) {
            if(empty($ids)) {
                continue;
            }
            Logs::info(__FUNCTION__ . ": db {$dbName} receive ids=" . implode('|', $ids));
            $mysql_conn = ClientFactory::getMysqlClient2( $dbName, 0 ) ;
            if( $mysql_conn == null ) {
                $err = ClientFactory::getError() ; 
                Logs::info( __FUNCTION__ . ": connect db faild, error={$err}, ids=" . print_r($ids, true) ) ;  
                continue;
            }

            foreach ($ids as $id) {
                if (strlen($id) < 6) {
                    continue;
                }
                if (!ctype_alpha($id{8})) {
                    $qnd = News_QQNewsData::getInstance() ;
                    $tmp = $qnd->getNewsContents(array($id));
                    if ($tmp != false && count($tmp) > 0) {
                        $news[] = array_shift($tmp);
                    } else {
                        Logs::info(__FUNCTION__ . ": get news data failed, id=$id");
                        $msg = __FUNCTION__ .": get news data failed! cgi=" . $_SERVER['SCRIPT_URL'] . " id=" . $id;
                        if(isset($_GET['tagid'])) {
                            $msg .= ', tagid=' . $_GET['tagid'];
                        } else if(isset($_GET['chlid'])) {
                            $msg .= ', chlid=' . $_GET['chlid'];
                        }
                        ItilLogs::ItilErrLog("GET_NEWS_DATA", 0, 0, ItilLogs::LVL_ERROR, 1, $msg);
                    }
                    continue;
                }
                $tableName = $this->getTableNameFromId($id);
                $id = mysql_real_escape_string($id, $mysql_conn);
                $sql = "select id, content from {$tableName} where id='{$id}'" ;
                Logs::info(__FUNCTION__ . ": db={$dbName}, sql={$sql}");
                $rs = mysql_query( $sql, $mysql_conn ) ;
                if( !$rs ) {
                    Logs::info( __FUNCTION__ . ": mysql_query faild, error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                    continue;
                }
                $rd = mysql_fetch_assoc($rs);
                if( !$rd ) {
                    Logs::info( __FUNCTION__ . ": mysql_fetch_assoc failed. error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                    $msg = __FUNCTION__ .": get news data failed! cgi=" . $_SERVER['SCRIPT_URL'] . " id=" . $id;
                    if(isset($_GET['tagid'])) {
                        $msg .= ', tagid=' . $_GET['tagid'];
                    } else if(isset($_GET['chlid'])) {
                        $msg .= ', chlid=' . $_GET['chlid'];
                    }
                    ItilLogs::ItilErrLog("GET_NEWS_DATA", 0, 0, ItilLogs::LVL_ERROR, 1, $msg);
                    continue;
                }
                $tmp['id'] = $rd['id'];
                $tmp['content'] = $rd['content'];
                $news[] = $tmp;
            }
        }
        return $news;
    }

    public function getNewsContentFromDB($ids) 
    {
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;
        if( $this->_mysql_conn == null ) {
            $err = ClientFactory::getError() ; 
            Logs::info( "getNewsContentFromDB: connect db faild, error={$err}, ids=" . print_r($ids, true) ) ;  
            return false ; // 数据库连接失败。 
        }

        $news = array();
        foreach ($ids as $id) {
            if (strlen($id) < 6) {
                continue;
            }
            //兼容抓取的文章，这个位置是字母C、D等
            //if (strval(substr($id, 8, 1)) !== 'A') {
            if (!ctype_alpha($id{8})) {
                $qnd = News_QQNewsData::getInstance() ;
                $tmp = $qnd->getNewsContents(array($id));
                if ($tmp != false && count($tmp) > 0) {
                    $news[] = array_shift($tmp);
                } else {
                    Logs::info(__FUNCTION__ . ": get news data failed, id=$id");
                    $msg = __FUNCTION__ .": get news data failed! cgi=" . $_SERVER['SCRIPT_URL'] . " id=" . $id;
                    if(isset($_GET['tagid'])) {
                        $msg .= ', tagid=' . $_GET['tagid'];
                    } else if(isset($_GET['chlid'])) {
                        $msg .= ', chlid=' . $_GET['chlid'];
                    }
                    ItilLogs::ItilErrLog("GET_NEWS_DATA",
                            0,
                            0,      
                            ItilLogs::LVL_ERROR,
                            1,
                            $msg);
                }
                continue;
            }
            $tableName = $this->getTableNameFromId($id);
            $id = mysql_real_escape_string($id, $this->_mysql_conn);
            $sql = "select id, content from {$tableName} where id='{$id}'" ;
            Logs::info($sql);
            $rs = mysql_query( $sql, $this->_mysql_conn ) ;
            if( !$rs ) {
                Logs::info( "getNewsContentFromDB: mysql_query faild, error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                continue;
            }
            $rd = mysql_fetch_assoc($rs);
            if( !$rd ) {
                Logs::info( "getNewsContentFromDB: mysql_fetch_assoc failed. error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                $msg = __FUNCTION__ .": get news data failed! cgi=" . $_SERVER['SCRIPT_URL'] . " id=" . $id;
                if(isset($_GET['tagid'])) {
                    $msg .= ', tagid=' . $_GET['tagid'];
                } else if(isset($_GET['chlid'])) {
                    $msg .= ', chlid=' . $_GET['chlid'];
                }
                ItilLogs::ItilErrLog("GET_NEWS_DATA",
                        0,
                        0,      
                        ItilLogs::LVL_ERROR,
                        1,
                        $msg);
                continue;
            }
            $tmp['id'] = $rd['id'];
            $tmp['content'] = $rd['content'];
            $news[] = $tmp;

        }
        return $news;
    }

    //分表策略
    private function getTableNumWxNews($id)
    {
        $month = substr($id, 0, 6);
        $day = substr($id, 0, 8);
        if($day >= '20141025') {
            $day = substr($day, -2);
            return $month . '_' . intval($day / 8);
        } else {
            return $month;
        }
    }

    //按微信文章id获取对应的数据库名
    protected function getWxDBNames($ids)
    {
        $wxDbNames = array();
        foreach($ids as $id) {
            if (strlen($id) < 6) {
                continue;
            }
            if(substr($id, 0, 6) <= '201506') {
                $wxDbNames['WX_NEWS_BAK1_R'][] = $id;
            } elseif(substr($id, 0, 6) <= '201606') {
                $wxDbNames['WX_NEWS_BAK2_R'][] = $id;
            } else {
                $wxDbNames['WX_SRCDATA_R'][] = $id;
            }
        }
        return $wxDbNames;
    }

    //从wx文章数据库获取文章
    public function getWxNewsContentFromDB($wxIds, &$not_exist_ids) 
    {
        $wxDbNames = $this->getWxDBNames($wxIds);
        if(empty($wxDbNames)) {
            return false;
        }
        $news = array();
        foreach($wxDbNames as $dbName=>$ids) {
            if(empty($ids)) {
                continue;
            }
            Logs::info(__FUNCTION__ . ": db {$dbName} receive ids=" . implode('|', $ids));
            $mysql_conn = ClientFactory::getMysqlClient2( $dbName, 0 ) ;
            if( $mysql_conn == null ) {
                $err = ClientFactory::getError() ; 
                Logs::info( __FUNCTION__ . ": connect db faild, error={$err}, ids=" . print_r($ids, true) ) ;  
                continue;
            }

            foreach ($ids as $id) {
                if (strlen($id) < 6) {
                    continue;
                }

                $num = $this->getTableNumWxNews($id);
                $tableName = 'wx_news_content_' . $num;
                $id = mysql_real_escape_string($id, $mysql_conn);
                $cmsid = substr($id, 0, -2);
                $sql = "select id, content from {$tableName} where cmsid='{$cmsid}'" ;
                Logs::info($sql);
                $rs = mysql_query( $sql, $mysql_conn ) ;
                if( !$rs ) {
                    Logs::info( __FUNCTION__ . ": mysql_query faild, error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                    continue;
                }
                $rd = mysql_fetch_assoc($rs);
                if( !$rd ) {
                    Logs::info( __FUNCTION__ . ": mysql_fetch_assoc failed. error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
                    $msg = __FUNCTION__ .": get wx news data failed! cgi=" . $_SERVER['SCRIPT_URL'] . " id=" . $id;
                    ItilLogs::ItilErrLog("GET_WX_NEWS_DATA",
                            0,
                            0,      
                            ItilLogs::LVL_ERROR,
                            1,
                            $msg);
                    $not_exist_ids[] = $id;
                    continue;
                }
                $tmp['id'] = $rd['id'];
                $tmp['content'] = $rd['content'];
                $news[] = $tmp;
            }
            mysql_close($mysql_conn);
        }
        return $news;
    }

    public function getCardInfoFromDB() {
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;
        if( $this->_mysql_conn == null ) {
            $err = ClientFactory::getError() ; 
            Logs::info( "getCardInfoFromDB: connect db faild, error={$err}, ids=" . print_r($ids, true) ) ;  
            return false ; // 数据库连接失败。 
        }
        $cards = array();
        //$id = mysql_real_escape_string($chlid, $this->_mysql_conn);
        $sql = "select chlid,chlname,mrk,sicon,icon from sub_media_info";
        Logs::info($sql);
        $rs = mysql_query( $sql, $this->_mysql_conn ) ;
        if(!$rs) {
            Logs::info( "getCardInfoFromDB: mysql_query faild, error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
            return false;
        }
        $rd = mysql_fetch_assoc($rs);
        if( !$rd ) {
            Logs::info( "getCardInfoFromDB: mysql_fetch_assoc failed. error=" . mysql_error() . 'ids=' . print_r($ids, true) ) ;
            return false;
        }
        while($rd) {
            $card = array();
            $card['chlid'] = $rd['chlid'];
            $card['info']['chlname'] = $rd['chlname'];
            $card['info']['desc'] = $rd['mrk'];
            $card['info']['icon'] = $rd['icon'];
            $cards[] = $card;
            $rd = mysql_fetch_assoc($rs);
        }
        return $cards;

    }

    public function getAllChlid()
    {
        //get from mc
        $chlids = array();
        if($this->_mc == null)
        {
            Logs::info(__FUNCTION__ . ": mc is null");
        }
        else
        {
            $key = MC_CHANNEL_MEDIA_INFO_PRE . "all";
            $chlidInfo = $this->_mc->get($key);
            if( !empty($chlidInfo))
            {
                Logs::info(__FUNCTION__ . ": get from mc");
                foreach ($chlidInfo as $item) {
                    if ($item['contentType'] != '10000') {
                        $chlids[] = $item['chlid'];
                    }
                }
                return $chlids;
            }
            else
            {
                Logs::info(__FUNCTION__ . ": {$key} is empty in mc");
            }
        }
        //get from db
        $chlids = array();
        $cards = $this->getCardInfoFromDB();
        if($cards == false)
        {
            Logs::info(__FUNCTION__ . ": get chlids from db failed..");
            return false;
        }
        foreach($cards as $card)
        {
            if ($card['contentType'] != '10000') {
                $chlids[] = $card['chlid'];
            }
        }
        Logs::info(__FUNCTION__ . ": get chlids from db");
        if($this->_mc != null)
        {
            $key = MC_CHANNEL_MEDIA_INFO_PRE . "all";
            $this->_mc->set($key, $chlids);
            Logs::info(__FUNCTION__ . ": save chlids to mc");
        }
        else
        {
            Logs::info(__FUNCTION__ . ": mc is null, so can't save to mc");
        }
        return $chlids;
    }

    public function getMediaInfoByTag($tag = '') {
        //改为从名字服务获取tag到chlid的转换
        $key = md5($tag) . '.tag.qqnews.dic';
        $value = '';
        getValueByKey($key, $value);
        $chlid = intval($value);

        //保险起见，失败的话还从文件载入
        if(empty($chlid))
        {
            Logs::info(__FUNCTION__ . ": get chlid by tag from name service failed!!");
            $tagToChlid = SubNews_TagToChlid::getInstance()->getTagToChlid();
            $chlid = $tagToChlid[$tag];
        }

        if (!empty($chlid)) {
            $info = $this->getSubMediaInfo(array($chlid));
            foreach ($info as $tmp) {
                if($tmp['contentType'] == '10000') continue;
                if (strval($tmp['chlid']) === strval($chlid)) {
                    return $tmp;
                }
            }
        }
        return false;
    }

    public function getMediaIdFromUin($uin) {
        $des = DES::getInstance();
        $key = MC_MEDIA_UIN_PRE . $des->encodeUid($uin);
        Logs::info(__FUNCTION__ . ': check for key ' . $key);
        $ret = $this->_mc->get($key);
        if (isset($ret['chlid']) && strlen($ret['chlid']) > 0) {
            return strval($ret['chlid']) ;
        }
        return false;
    }

    //获得热门分类下的媒体信息
    public function getTopicInfos($key)
    {
        $channels = apc_fetch("APC." . $key);
        if(empty($channels))
        {
            if($this->_mc == null)
            {
                Logs::info(__FUNCTION__ . ": mc is null!");
                return false;
            }
            $channels = $this->_mc->get($key);
            if(empty($channels))
            {
                Logs::info(__FUNCTION__ . ": get from cache failed!");
                return false;
            }
            Logs::info(__FUNCTION__ . ": get from mc");
            apc_store("APC." . $key, $channels, APC_TOPIC_EXPIRE_TIME);
            Logs::info(__FUNCTION__ . ": save to apc");
        }
        else
        {
            Logs::info(__FUNCTION__ . ": get from apc");
        }

        $chlids = array();
        foreach($channels as $one)
        {
            $chlids[] = $one['chlid'];
        }
        $chlinfos = $this->getSubMediaInfo($chlids);
        $index = 0;
        foreach ($chlinfos as $item) {
            $tmp = &$channels[$index];
            $tmp['chlid'] = $item['chlid'];
            $tmp['chlname'] = $item['chlname'];
            $tmp['icon'] = $item['icon'];
            $tmp['sicon'] = $item['sicon'];
            $tmp['desc'] = $item['mrk'];
            $tmp['subCount'] = intval($item['subCount']);
            $tmp['keywords'] = $item['keywords'];
            $tmp['uin'] = $item['uin'];
            $tmp['intro'] = $item['regDesc'];
            $tmp['recommend'] = $item['recommend'];
            $index++;
        }
        return $channels;
    }

    public function getSubMediaInfo($chlids=array(), $order = 'sort', $fromDb = 0) {
        static $_all_media_info;
        $sub_media_info = array();
        if (count($chlids) > 0) {
            $not_save_chlids = array();
            foreach ($chlids as $chlid) {
                if (isset($_all_media_info[$chlid])) {
                    $sub_media_info[] = $_all_media_info[$chlid];
                } else {
                    $not_save_chlids[] = $chlid;
                }
            }
            if (count($not_save_chlids) == 0) {
                Logs::info(__FUNCTION__ . ": get from static var");
                return $sub_media_info;
            } else {
                $chlids = $not_save_chlids;
            }
        }

        // get from mc
        if ($this->_mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
        } else {
            $key_pre = MC_MEDIA_INFO_PRE;
            if (count($chlids) <= 0) {
                $chlids = apc_fetch(APC_CHLID_INFO_PRE . "chlids");
                if ($chlids == false) {
                    $key = $key_pre . 'chlids';
                    $chlids = $this->_mc->get($key);
                    Logs::info(__FUNCTION__ . ": get chlids from mc");
                    $apc_timeout = intval(APC_SUB_CHLID_INFO_TIME);
                    apc_store(APC_CHLID_INFO_PRE . "chlids", $chlids, $apc_timeout);
                } else {
                    Logs::info(__FUNCTION__ . ": get chlids from apc");
                }
            }
            if ($chlids != null && is_array($chlids) && count($chlids) > 0) {
                $keys = array();
                $apc_data = array();
                foreach ($chlids as $chlid) {
                    if(empty($chlid) || !is_numeric($chlid)) continue;
                    $apc_chlid_info = apc_fetch(APC_CHLID_INFO_PRE . $chlid);
                    if ($apc_chlid_info != false) {
                        $apc_data[] = $apc_chlid_info;
                    } else {
                        $key = $key_pre . $chlid;
                        $keys[] = $key;
                    }
                }
                if ($keys != array()) {
                    //$mc_info = $this->_mc->get_multi($keys);
                    /*$mc_info = $this->getMcMultiSlice($keys);
                    if ($mc_info != null && is_array($mc_info) && count($mc_info) > 0) {
                        $apc_timeout = intval(APC_SUB_CHLID_INFO_TIME);
                        foreach ($mc_info as $info) {
                            $apc_timeout_now = mt_rand($apc_timeout * 0.9, $apc_timeout * 1.1);
                            apc_store(APC_CHLID_INFO_PRE . strval($info['chlid']), $info, $apc_timeout_now);
                        }
                        if (count($apc_data) > 0) {
                            $mc_info = array_merge($mc_info, $apc_data);
                            Logs::info(__FUNCTION__ . ": get chlid info from apc and mc");
                        } else {
                            Logs::info(__FUNCTION__ . ": get chlid info from mc");
                        }
                    } else {*/
                        $redis_info = $this->getRedisMediaInfo($keys);
                        if ($redis_info != null && is_array($redis_info) && count($redis_info) > 0) { 
                            $apc_timeout = intval(APC_SUB_CHLID_INFO_TIME);
                            foreach ($redis_info as $info) {
                                $apc_timeout_now = mt_rand($apc_timeout * 0.9, $apc_timeout * 1.1);
                                apc_store(APC_CHLID_INFO_PRE . strval($info['chlid']), $info, $apc_timeout_now);
                            }    
                            if (count($apc_data) > 0) { 
                                $mc_info = array_merge($redis_info, $apc_data);
                                Logs::info(__FUNCTION__ . ": get chlid info from apc and redis");
                            } else {
                                $mc_info = $redis_info;
                                Logs::info(__FUNCTION__ . ": get chlid info from redis");
                            }    
                        } else {
                            $mc_info = $apc_data;
                            //Logs::info(__FUNCTION__ . ": get chlid info from apc");
                        } 
                    //}
                } else {
                    $mc_info = $apc_data;
                    //Logs::info(__FUNCTION__ . ": get chlid info from apc");
                }
                if ($mc_info != null && is_array($mc_info) && count($mc_info) > 0) {
                    foreach ($mc_info as $info) {
                        $sub_media_info[] = $info;
                        $_all_media_info[$info['chlid']] = $info;
                    }
                    return $sub_media_info;
                } else {
                    Logs::info(__FUNCTION__ . ": mc get_multi failed! keys = " . print_r($keys, true));
                    $keys_splice = array_slice($chlids, 0, 5);
                    $keys_str = implode(',', $keys_splice);
                    $uri = $_SERVER['REQUEST_URI'];
                    if ($pos = strpos($uri, '?')) {
                        $uri = substr($uri, 0, $pos);
                    }
                    ItilLogs::ItilErrLog(__CLASS__,
                            0,
                            0,      
                            ItilLogs::LVL_ERROR,
                            1,
                            __FUNCTION__ .": mc get_multi failed! cgi=" . $uri . " chlids=" . $keys_str);
                }
            } else {
                Logs::info(__FUNCTION__ . ": {$key} is empty in mc");
            }
        }

        //有redis，不用透到db了
        return $sub_media_info;

        if($fromDb != 1) {
            return $sub_media_info;
        }

        $key = 'dbswitch.mediainfo.qq.com';
        $value = '';
        getValueByKey($key , $value);
        if(intval($value) != 1) { 
            return $sub_media_info;
        } 

        // get from db
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;
        if ($this->_mysql_conn == null) {
            $err = ClientFactory::getError();
            Logs::info(__FUNCTION__ . ": connect db faild, error={$err}");  
            return false;
        }

        $chlidStr = implode(',', $chlids);
        $chlidStr = str_ireplace('sleep', '', $chlidStr);
        $chlidStr = mysql_real_escape_string($chlidStr, $this->_mysql_conn);
        //$sql = "select * from sub_media_info order by `{$order}`";
        $sql = "select * from sub_media_info where chlid in ('{$chlidStr}')";
        Logs::info(__FUNCTION__ . ": sql=" . $sql);
        $rs = mysql_query($sql, $this->_mysql_conn);
        if (!$rs) {
            Logs::info(__FUNCTION__ . ": mysql_query faild, error=" . mysql_error());
            return false;
        }
        $rd = mysql_fetch_assoc($rs);
        if (!$rd) {
            Logs::info(__FUNCTION__ . ": mysql_fetch_assoc failed. error=" . mysql_error());
            return 0;
        }
        $des = DES::getInstance();
        while ($rd != false) {
            $rd['uin'] = $des->encodeUid($rd['uin']);
            $rd['chlid'] = strval($rd['chlid']);
            $sub_media_info[] = $rd;
            $rd = mysql_fetch_assoc($rs);
        }
        Logs::info(__FUNCTION__ . ": get from db");

        // tmp save infos to mc, need delete!
        $mediaInfo = array();
        if ($this->_mc != null) {
            $key_pre = MC_MEDIA_INFO_PRE;
            //$allChlids = array();
            foreach ($sub_media_info as $info) {
                $key = $key_pre . $info['chlid'];
                $this->_mc->set($key, $info);
                //$allChlids[] = $info['chlid'];
                if ($chlids == null || in_array($info['chlid'], $chlids)) {
                    $mediaInfo[] = $info;
                }
            }
            //$key = $key_pre . 'chlids';
            //$this->_mc->set($key, $allChlids);
            Logs::info(__FUNCTION__ . ": save medias info to mc");
        } else {
            Logs::info(__FUNCTION__ . ": mc is null, so can't save to mc");
        }

        return $mediaInfo;
    }

    //根据分组获取媒体
    public function getMediaInfoByType($contentType, $order = 'sort') {
        $sub_media_info = array();

        // get from mc
        if ($this->_mc == null) 
        {
            Logs::info(__FUNCTION__ . ": mc is null");
        } 
        else 
        {
            $all_sub_media_info = $this->getSubMediaInfo();
            if (empty($contentType)) {
                $sub_media_info = $all_sub_media_info;
            } else {
                foreach ($all_sub_media_info as $media_info) {
                    if ($media_info['contentType'] == $contentType) {
                        $sub_media_info[] = $media_info;
                    }
                }
            }
            if (count($sub_media_info) != 0) {
                Logs::info(__FUNCTION__ . ": get from api getSubMediaInfo");
                return $sub_media_info;
            }

            $key_pre = MC_CHANNEL_MEDIA_INFO_PRE;
            if ( empty($contentType) )
            {
                $key = $key_pre . 'all';
            }
            else
            {
                $key = $key_pre . $contentType;
            } 
            Logs::info(__FUNCTION__ . ": key is {$key}");
            $mc_info = $this->_mc->get($key);
            if ($mc_info != null && is_array($mc_info) && count($mc_info) > 0) 
            {
                Logs::info(__FUNCTION__ . ": get from mc using key={$key}");
                return $mc_info;
            }
            else 
            {
                Logs::info(__FUNCTION__ . ": mc get value failed! key = " . $key);
            }
        }

        // get from db
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;
        if ($this->_mysql_conn == null) 
        {
            $err = ClientFactory::getError();
            Logs::info(__FUNCTION__ . ": connect db faild, error={$err}");  
            return false;
        }

        if( !empty($contentType) )
        {
            $contentType = str_ireplace('sleep', '', $contentType);
            $contentType = mysql_real_escape_string($contentType, $this->_mysql_conn);
            $sql = "select * from sub_media_info where contentType='{$contentType}' order by `{$order}`";
        }
        else
        {
            $sql = "select * from sub_media_info order by `{$order}`";
        }
        $rs = mysql_query($sql, $this->_mysql_conn);
        if (!$rs) {
            Logs::info(__FUNCTION__ . ": mysql_query faild, error=" . mysql_error());
            return false;
        }
        $rd = mysql_fetch_assoc($rs);
        if (!$rd) {
            Logs::info(__FUNCTION__ . ": mysql_fetch_assoc failed. error=" . mysql_error());
            return 0;
        }
        $des = DES::getInstance();
        while ($rd != false) 
        {
            $rd['uin'] = $des->encodeUid($rd['uin']);
            $sub_media_info[] = $rd;
            $rd = mysql_fetch_assoc($rs);
        }
        Logs::info(__FUNCTION__ . ": get from db");

        // tmp save infos to mc, need delete!
        if ($this->_mc != null) 
        {
            $key_pre = MC_CHANNEL_MEDIA_INFO_PRE;
            if(!empty($contentType))
            {
                $key = $key_pre . $contentType;
            }
            else
            {
                $key = $key_pre . 'all';
            }
            $this->_mc->set($key, $sub_media_info);
            Logs::info(__FUNCTION__ . ": save channel medias info to mc using key={$key}");
        } else {
            Logs::info(__FUNCTION__ . ": mc is null, so can't save to mc");
        }

        return $sub_media_info;
    }

    private function getSubMediaType($order = 'sort') {
        
        $sub_media_type = apc_fetch("SUB.MEDIA.TYPE.ALL");
        if ($sub_media_type != false) {
            return $sub_media_type;
        }
	
	$sub_media_type = array();

        // get from mc
        if ($this->_mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
        } else {
            $key_pre = MC_MEDIA_TYPE_PRE;
            $key = $key_pre . 'content_types';
            $types = $this->_mc->get($key);
            if ($types != null && is_array($types) && count($types) > 0) {
                $keys = array();
                foreach ($types as $type) {
                    $key = $key_pre . $type;
                    $keys[] = $key;
                }
                if (count($keys) > 20) {
                    Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
                    ItilLogs::ItilErrLog(__CLASS__,
                            0,      
                            0,      
                            ItilLogs::LVL_ERROR,
                            1,      
                            __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
                }
                $mc_type = $this->_mc->get_multi($keys);
                if ($mc_type == null || !is_array($mc_type) || count($mc_type) == 0) {
                    Logs::info(__FUNCTION__ . ": mc get_multi failed! keys = " . print_r($keys, true));
                } else {
                    foreach ($mc_type as $type) {
                        $sub_media_type[] = $type;
                    }
                    apc_store("SUB.MEDIA.TYPE.ALL", $sub_media_type, 30);
                    Logs::info(__FUNCTION__ . ": get from mc");
                    return $sub_media_type;
                }
            } else {
                Logs::info(__FUNCTION__ . ": {$key} is empty in mc");
            }
        }
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_SUB_R', 0 ) ;

        // get from db
        if ($this->_mysql_conn == null) {
            $err = ClientFactory::getError();
            Logs::info(__FUNCTION__ . ": connect db faild, error={$err}");  
            return false;
        }

        $sql = "select * from sub_media_type order by `{$order}`";
        $rs = mysql_query($sql, $this->_mysql_conn);
        if (!$rs) {
            Logs::info(__FUNCTION__ . ": mysql_query faild, error=" . mysql_error());
            return false;
        }
        $rd = mysql_fetch_assoc($rs);
        if (!$rd) {
            Logs::info(__FUNCTION__ . ": mysql_fetch_assoc failed. error=" . mysql_error());
            return false;
        }
        while ($rd != false) {
            $sub_media_type[] = $rd;
            $rd = mysql_fetch_assoc($rs);
        }
        Logs::info(__FUNCTION__ . ": get from db");

        // tmp for save media type, need to delete
        if ($this->_mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null, so can't save to mc");
        } else {
            $key_pre = MC_MEDIA_TYPE_PRE;
            $types = array();
            foreach ($sub_media_type as $type) {
                $key = $key_pre . $type['contentType'];
                $this->_mc->set($key, $type);
                $types[] = $type['contentType'];
            }
            $key = $key_pre . "content_types";
            $this->_mc->set($key, $types);
            Logs::info(__FUNCTION__ . ": save to mc succeed");
        }

        return $sub_media_type;
    }

    public function getSubList() {
        $sub_media_info = $this->getSubMediaInfo();
        if ($sub_media_info == false) {
            Logs::info(__FUNCTION__ . ": getSubMediaInfo failed!");
            return false;
        }

        $tree = array();
        foreach ($sub_media_info as $info) {
            $tree[] = array(
                    'chlid' => strval($info['chlid']), 
                    'chlname' => $info['chlname'], 
                    'icon' => $info['icon'], 
                    'desc' => $info['mrk'], 
                    'sicon' => $info['sicon'], 
                    'intro' => $info['regDesc'], 
                    'subCount' => intval($info['subCount'])
                    );
        }

        return $tree;
    }

    //获取单条媒体信息 add by dinazhang
    public function getSubItem($chlid)
    {
        $sub_media_info = $this->getSubMediaInfo(array($chlid), 'sort', 1);
        Logs::info("sub = " . print_r($sub_media_info,true));
        if ($sub_media_info == false) 
        {
            Logs::info(__FUNCTION__ . ": getSubMediaInfo failed!");
            return false;
        }

        $tree = array();
        foreach($sub_media_info as $info)
        {
            if($info['contentType'] == 10000) continue;
            $tree = array(
                    'chlid' => strval($info['chlid']), 
                    'chlname' => $info['chlname'], 
                    'icon' => $info['icon'], 
                    'sicon' => $info['sicon'],
                    'desc' => $info['mrk'], 
                    'subCount' => intval($info['subCount']),
                    'keywords' => $info['keywords'],
                    'uin' => $info['uin'],
                    'intro' => $info['regDesc'], 
                    'recommend' => $info['recommend']
                    );  
            if(!empty($info['wechat'])) {
                $tree['wechat'] = $info['wechat'];
            }
        }
        return $tree ;
    }

    private function splitKeywordsToAlias($keywords) {
        $alias = array();
        foreach (explode(';', $keywords) as $token) {
            if (strlen($token) <= 0) {
                continue;
            }
            $tmp = array();
            $type = ctype_alpha($token) ? 1 : 0;
            $tmp['flag'] = $type;
            $tmp['token'] = $token;
            $alias[] = $tmp;
        }
        return $alias;
    }

    public function getCatVersionAndCounts($versionOnly=false)
    {
        if ($this->_mc == null) {
            Logs::info(__FUNCTION__ . ": mc is null");
            return false;
        } else {
            if($versionOnly) {
                $key = MC_MEDIA_INFO_PRE . 'version';
                $mcData = $this->_mc->get($key);
                if($mcData) {
                    return array('version'=>$mcData);
                }
            } else {
                $keys[] = MC_MEDIA_INFO_PRE . 'version';
                $keys[] = MC_MEDIA_INFO_PRE . 'subCounts';
                $mcData = $this->_mc->get_multi($keys);
                if($mcData) {
                    $version = $mcData[MC_MEDIA_INFO_PRE . 'version'];
                    $subCounts = $mcData[MC_MEDIA_INFO_PRE . 'subCounts'];
                    return array('version'=>$version, 'subCounts'=>$subCounts);
                }
            }
            
        }
        return false;

    }

    public function getCatList($with_channels = true, &$version = 0, &$subCounts = array(), $isTest=false) {

        if ($with_channels == true) {
            $sub_media_info = $this->getSubMediaInfo();
            if ($sub_media_info == false) {
                Logs::info(__FUNCTION__ . ": getSubMediaInfo failed!");
                return false;
            }

            $sort = array();
            foreach ($sub_media_info as $key => $info) {
                $sort[$key] = $info['sort'];
            }
            array_multisort($sort, SORT_DESC, $sub_media_info) ;

            $key = 'sub.appnews.com.catListVsionBase';
            $value = '';
            getValueByKey($key , $value);

            $version += intval($value);

            foreach ($sub_media_info as $info) {
                $version += intval($info['chlid']);
                $version += intval($info['contentType']);
                $subCounts[strval($info['chlid'])] = intval($info['subCount']);
                $medias[$info['contentType']][] = array(
                        'chlid' => strval($info['chlid']), 
                        'chlname' => $info['chlname'], 
                        'icon' => $info['icon'], 
                        'desc' => $info['mrk'], 
                        'sicon' => $info['sicon'], 
                        'subCount' => intval($info['subCount']),
                        'keywords' => $info['keywords'],
                        'uin' => $info['uin'], 
                        'intro' => $info['regDesc'], 
                        'recommend' => $info['recommend'],
                        'alias' => $this->splitKeywordsToAlias($info['keywords'])
                        );
            }
        }

        // select media cat info
        $sub_media_type = $this->getSubMediaType();
        if ($sub_media_type == false) {
            Logs::info(__FUNCTION__ . ": getSubMediaType failed!");
            return false;
        }

        $tree = array();
        $sort = array();
        foreach ($sub_media_type as $key => $type) {
            if($type['contentType'] == '10000'){
                continue ;
            }
            if ($with_channels == true) {
                if (count($medias[$type['contentType']]) > 0) {
                    $sort[$key] = $type['sort'];
                    $tree[] = array(
                            'catId' => $type['contentType'], 
                            'catName' => $type['typeName'], 
                            'icon' => '', //$type['icon'], 
                            'icon_hl' => '', //$type['icon_hl'], 
                            'micon' => '', //$type['micon'], 
                            'recommend' => $type['recommend'],
                            'channels' => $medias[$type['contentType']]
                            );
                }
            } else {
                $sort[$key] = $type['sort'];
                $tree[] = array(
                        'catId' => $type['contentType'], 
                        'catName' => $type['typeName'], 
                        'icon' => $type['icon'], 
                        'icon_hl' => $type['icon_hl'], 
                        'micon' => $type['micon'], 
                        'recommend' => $type['recommend'],
                        );
            }
        }
        array_multisort($sort, SORT_ASC, $tree) ;

        return $tree;
    }

    public function getChlInfo($chlids, $isTest = false, $supportWxSub = false) {
        if (!is_array($chlids) || count($chlids) == 0) {
            Logs::info(__FUNCTION__ . ": param error");
            return false;
        }

        $om_chlids = array();
        $wx_chlids = array();
        foreach($chlids as $chlid) {
            if(empty($chlid) || !is_numeric($chlid)) {
                continue;
            }
            //大于10w的号是微信的
            $chlidType = ClientFactory::jundgeChlid($chlid);
            if($chlidType === 'wx') {
                $wx_chlids[] = $chlid;
            } else if($chlidType === 'sub'){
                $om_chlids[] = $chlid;
            }
        }

        $tree = array();
        if(!empty($om_chlids)) {
            $sub_media_info = $this->getSubMediaInfo($om_chlids);
            if ($sub_media_info == false) {
                Logs::info(__FUNCTION__ . ": getSubMediaInfo failed!");
            } else {
                foreach ($sub_media_info as $info) {
                    if (in_array($info['chlid'], $om_chlids) == false) {
                        continue;
                    }
                    //正式环境跳过测试媒体
                    if($info['contentType'] == '10000') {
                        continue;
                    }

                    // 获取vip信息
                    $userinfo = QQSC::getInstance()->getChlidUserInfo('chl' . $info['chlid']);
                    if($userinfo) {
                        if($userinfo['vip_type']) {
                            $vip_type = $userinfo['vip_type'];
                        }   
                        if($userinfo['vip_desc']) {
                            $vip_desc = $userinfo['vip_desc'];
                        }   
                    } 

                    $tree[] = array(
                        'chlid' => strval($info['chlid']), 
                        'chlname' => $info['chlname'], 
                        'icon' => $info['icon'], 
                        'desc' => $info['mrk'], 
                        'sicon' => $info['sicon'], 
                        'catId' => $info['contentType'],
                        'subCount' => intval($info['subCount']),
                        'intro' => $info['regDesc'], 
                        'uin' => $info['uin'],
                        'keywords' => $info['keywords'],
                        'recommend' => $info['recommend'],
                        'lastArtUpdate' => $info['lastArtUpdate'],
                        'followState' => 1,  //followState: 0-未关注，1-已关注，2-互相关注，3-对方已关注
                        'vip' => $vip_type,         //0 普通人     1  大V  2  行业达人    3  普通达人
                        'cardType'=> 0,     //1 普通用户  0：媒体号
                        'disableFollowButton'=>0,  
                    );
                }
            }
        }
        if($supportWxSub && !empty($wx_chlids)) {
            Logs::info('get wx media info');
            $handler = SubNews_WxMediaInfoTool::getInstance();
            $wx_openids = $handler->getWxOpenIdByOmChlid($wx_chlids);
            $wxMediaInfos = $handler->getWxMediaInfo($wx_openids);
            !empty($wxMediaInfos) && $tree = array_merge($tree, $wxMediaInfos);
            
        }
        return $tree;
    }

    //通过chlid获得媒体下所有文章的索引
    public function getNewsIndexByChlid($chlid) {
        $news = array();
        $indexes = $this->getNewsIndexFromCache(array($chlid));
        $key = MC_INDEX_PRE . $chlid; 
        $index = $indexes[$key];
        if (!is_array($index) || count($index) == 0) {
            $index = $this->getNewsIndexFromDB($chlid);

            /*
               if ($index) {
               $this->setNewsIndexToMc($chlid, $index);
               Logs::info(__FUNCTION__ . ": save index to mc, chlid = {$chlid}");
               }
             */
        }
        return $index;
    }

    //某个媒体的文章列表
    public function getNewsByChlid($chlid = 0, $id = '0', $page = 0, $count=20) 
    {
        if ($chlid <= 0) 
        {
            return array();
        }
        $indexes = $this->getNewsIndex(array($chlid));
        return $this->getNewsByIndex($indexes, $id, $page, $count);
    }

    //某个分类下的文章列表
    public function getNewsByContentType($catid = 0, $id = '0', $page = 0, $count=20, $groupEnable = 0) 
    {
        if ($catid <= 0) 
        {
            return array();
        }
        $chlids = array();
        $medias = $this->getMediaInfoByType($catid);
        foreach($medias as $media)
        {
            $chlids[] = intval($media['chlid']);
        }

        $indexes = $this->getNewsIndex($chlids);
        $ret = $this->getNewsByIndex($indexes, $id, $page, $count, $groupEnable);
        $ret['catInfo'] = array('catId' => $catid);
        return $ret;
    }

    //某个分类下的文章列表含索引
    public function getNewsAndIndexesByCat($catid = 0, $count=20) 
    {
        if ($catid <= 0) 
        {
            return array();
        }
        $chlids = array();
        $medias = $this->getMediaInfoByType($catid);
        foreach($medias as $media)
        {
            $chlids[] = intval($media['chlid']);
        }

        $indexes = $this->getNewsIndex($chlids);
        $ret = array();
        $ret['ids'] = array_slice($indexes, 0, $count);
        $ret['news'] = $this->getNewsByIndex($indexes, '0', 0, $count);
        return $ret;
    }

    //通过索引获得多个媒体的文章列表,并按id+count翻页
    //indexes格式：[{id:12},{id:23}]
    public function getNewsByIndex($indexes, $id = '0', $page = 0, $count=20 , $groupEnable = 0) 
    {
        if (empty($indexes)) 
        {
            Logs::info(__FUNCTION__ ."there is no indexes..");
            return array('newslist'=>array(), 'total'=>0);
        }
        $idcount = 0;
        $flag = false;
        $ids = array();
        $total = count($indexes);
        //按page方式翻页
        if($page >= 0 )
        {
            $pageNum = ceil($total / $count);
            if($page >= $pageNum) 
            {
                //$page = $pageNum-1;
                return array('newslist'=>array(), 'total'=>0);
            }
            $offset = $page * $count; 
            $indexes = array_slice( $indexes, $offset, $count ); 
            foreach($indexes as $index)
            {
                $ids[] = $index['id'];
            }
        }
        //按id方式翻页
        else if($page < 0  && $id != '-1')
        {
            $preIndex = array();
            foreach($indexes as $index)
            {
                if($flag == false && $id == '0' )
                {
                    $flag = true;
                }
                if($flag == false && $id == $index['id'])
                {
                    $flag = true;
                }
                else if ( $flag == true )
                {
                    if($idcount < $count || ($groupEnable && $preIndex['order'] == $index['order']) ) 
                    {
                        $ids[] = $index['id'];
                        $preIndex = $index;
                        $idcount++;
                    } else {
                        break;
                    }
                }
            }
        }
        else
        {
            Logs::info(__FUNCTION__ . ": no slice page way");
            return array('newslist'=>array(), 'total'=>0);
        }
        $news = $this->getNews($ids);
        if($news === false)
        {
            Logs::info(__FUNCTION__. ": get news failed caused by old index number");
            return array('newslist'=>array(), 'total'=>0);
        }
        return array('newslist'=>$news, 'total'=>$total);
    }

    //2014-04-17 获取关键词聚合文章索引列表
    public function getTagNewsIndex($tagids, $lastid=0, $count=20) {
        $news = array();
        $noCacheTagids = array();
        $index = $this->getTagNewsIndexFromCache($tagids, $noCacheTagids);
        if (!is_array($index) || count($noCacheTagids) > 0) {
            foreach ($noCacheTagids as $tagid) {
                $tmpIndex = $this->getTagNewsIndexFromDB($tagid);

                if (is_array($tmpIndex) && $tmpIndex !== false) {
                    $this->setTagNewsIndexToMc($tagid, $tmpIndex);
                    Logs::info(__FUNCTION__ . ": save index to mc, tagid = " . $tagid);
                }

                $index[] = $tmpIndex;
            }
        }
        $index = $this->sortIndex($index);
        $ids = $this->getIdsFromIndex($index, $lastid, $count);

        return $ids;
    }

    private function getTagNewsIndexFromCache($tagids, &$noCacheTagids=array()) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            $err = 'mc init failed!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        } else if (!is_array($tagids)|| count($tagids) <= 0) {
            $err = 'tagids is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        $keys = array();
        foreach ($tagids as $tagid) {
            $keys[$tagid] = MC_INDEX_PRE . 'tag.' . $tagid;
        }
        if (count($keys) > 20) { 
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0,      
                    0,      
                    ItilLogs::LVL_ERROR,
                    1,      
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
        } 
        $news_index = $mc->get_multi($keys);
        foreach ($keys as $tagid => $key) {
            if (isset($news_index[$key]) === false) {
                Logs::info(__FUNCTION__ . ": no index in mc. key = {$key}");
                $noCacheTagids[] = $tagid;
            }
        }
        if ($news_index == null || !is_array($news_index)) {
            Logs::info(__FUNCTION__ . ": mc return null, news_index = " . print_r($news_index, true));
            return array();
        }
        return $news_index;
    }

    private function getTagsNewsFromTagIndex($tagids, $reqnum=3)
    {
        $redis = ClientFactory::getRedisClient('NEWS_TAG_INFO_R');
        if($redis == false){
            $err = 'redis init failed!'; 
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }else if (!is_array($tagids)|| count($tagids) <= 0) {
            $err = 'tagids is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        $keys = array();
        foreach($tagids as $tagid){
            $keys[$tagid] = "MRI_".$tagid;
        }
        if(count($keys) > 20){
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".implode('|', $keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0, 
                    0,
                    ItilLogs::LVL_ERROR,
                    1,
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));
        }
        $result = $redis->mget($keys);
        $k = 0;
        $news_index = array();
        foreach($keys as $tagid=>$key){
            $index = json_decode($result[$k],true);
            foreach($index as $newid=>$time){
                $tmp = array();
                $tmp['id'] = strval($newid);
                $tmp['tagid'] = strval($tagid);
                $tmp['timestamp'] = intval($time);
                $news_index[$tagid][] = $tmp;
            }
            $k++;
        }
        Logs::info("+=====".print_r($news_index,true));
        return $news_index;
    }

    private function getTagsNewsFromSearchAndCache($tagsinfo, $reqnum = 20) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            $err = 'mc init failed!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        } else if (!is_array($tagsinfo)|| count($tagsinfo) <= 0) {
            $err = 'tagsinfo is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        $keys = array();
        foreach ($tagsinfo as $tagid=>$tagname) {
            $keys[$tagname] = "Search".$tagid;
        }

        if (count($keys) > 20) { 
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0,      
                    0,      
                    ItilLogs::LVL_ERROR,
                    1,      
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
        } 
        $news_index = $mc->get_multi($keys);
        foreach ($tagsinfo as $tagid => $tagname) {
            $key = "Search".$tagid;
            if (isset($news_index[$key]) === false) {
                Logs::info(__FUNCTION__ . ": no index in mc. key = {$key},name={$tagname}");
                $noCacheTagids[$tagid] = $tagname;
            }
        }

        if ($news_index == null || !is_array($news_index)) {
            Logs::info(__FUNCTION__ . ": mc return null, news_index = " . print_r($news_index, true));
            $news_index = array();
        }

        if(!$noCacheTagids){
            Logs::info(__FUNCTION__ . ": no noCacheTags");
            return $news_index;
        }

        require_once('/usr/local/zk_agent/names/nameapi.php');
        $value = '';
        getValueByKey('soso_tag_num' , $value);
        $soso_num = 30;
        if(intval($value) > 0) {
            $soso_num = intval($value);
        }

        //流控,限制三十个tag搜索,并加入缓存10分钟
        $noCacheTagids = array_slice($noCacheTagids, 0, $soso_num, true);

        $page = 1;
        $count = $reqnum;
        $handler = Search_SearchNewHandler::getInstance();
        $ret = $handler->multiquery($noCacheTagids, $page, intval($count));
        if($ret && $mc){
            foreach($ret as $tagid=>$item){
                if($item){
                    $key = "Search".$tagid;
                    $mc->set($key, $item, APC_TOPIC_EXPIRE_TIME);
                }
            }
        }
        if($ret && is_array($ret)){
            return array_merge($news_index, $ret);
        }else{
            return $news_index;
        }

    }
    private function getTagsThreeNewsFromCache($tagids, &$noCacheTagids=array(),$reqnum = 3) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            $err = 'mc init failed!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        } else if (!is_array($tagids)|| count($tagids) <= 0) {
            $err = 'tagids is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        if($reqnum == 3){
            $tagkey = 'tagnews.';
        }else{
            $tagkey = 'BIGTAG.';
        }

        $keys = array();
        foreach ($tagids as $tagid) {
            $keys[$tagid] = MC_INDEX_PRE . $tagkey . $tagid;
        }
        if (count($keys) > 20) { 
            Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys));
            ItilLogs::ItilErrLog(__CLASS__,
                    0,      
                    0,      
                    ItilLogs::LVL_ERROR,
                    1,      
                    __FUNCTION__ . ": get_multi too many keys. count = " . count($keys));  
        } 
        $news_index = $mc->get_multi($keys);
        foreach ($keys as $tagid => $key) {
            if (isset($news_index[$key]) === false) {
                Logs::info(__FUNCTION__ . ": no index in mc. key = {$key}");
                $noCacheTagids[] = $tagid;
            }
        }
        if ($news_index == null || !is_array($news_index)) {
            Logs::info(__FUNCTION__ . ": mc return null, news_index = " . print_r($news_index, true));
            return array();
        }
        return $news_index;
    }

    private function setTagNewsIndexToMc($tagid, $index) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc init failed!");
            return false;
        }
        $key = MC_INDEX_PRE . 'tag.' .$tagid;
        $mc->set($key, $index, MC_EXPIRE_TIME);
    }

    private function setTagsThreeNewsToMc($tagid, $index) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc init failed!");
            return false;
        }
        $key = MC_INDEX_PRE . 'tagnews.' .$tagid;
        $mc->set($key, $index, MC_EXPIRE_TIME);
    }

    private function getTagNewsIndexFromDB($tagid,$reqnum=200) {
        $this->_mysql_conn = ClientFactory::getMysqlClient2( 'QQNEWS_KEYWORDS', 0 ) ;
        if( $this->_mysql_conn == null ) {
            $err = ClientFactory::getError() ; 
            Logs::info( __FUNCTION__ . ": connect db faild, chlid={$tagid}, error={$err}" ) ;  
            return false ; // 数据库连接失败。 
        }
        $num = $reqnum;

        $num = ClientFactory::getDbTableNum('QQNEWS_KEYWORDS', ($tagid % 100));
        $tblName = 'tag_news_index_' . $num ;

        $tagid = str_ireplace('sleep', '', $tagid);
        $tagid = mysql_real_escape_string($tagid, $this->_mysql_conn);
        $sql = "select distinct `id`, `tagid`, `order`, `cid` from {$tblName} where tagid='{$tagid}' order by `order` limit {$num};";
        Logs::info(__FUNCTION__ . ":" . $sql);
        $rs = mysql_query( $sql, $this->_mysql_conn ) ;
        if( !$rs ) {
            Logs::info( __FUNCTION__ . ": mysql_query faild, tagid={$tagid}, error=" . mysql_error()  ) ;
            return false ; // 数据库执行失败。       
        }
        $rd = mysql_fetch_assoc($rs); 
        $index = array();

        while( $rd != false ) {
            $index[] = array('id' => $rd['id'], 'order'=> $rd['order'], 'cid'=>$rd['cid'], 'tagid'=>$rd['tagid']);
            $rd = mysql_fetch_assoc($rs);
        }       
        return $index;
    }

    //分片getmulti
    private function getMcMultiSlice($keys, $slice_num=600)
    {
        $mc_info = array();
        $keys_count = count($keys);
        if($keys_count > $slice_num)
        {
            Logs::info(__FUNCTION__ . ": too much keys, need slice, num=" . $keys_count);
            for($i = 0; $i < $keys_count; $i += $slice_num)
            {
                $keys_slice = array_slice($keys, $i, $slice_num);
                if (count($keys_slice) > 20) { 
                    Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys_slice));
                    ItilLogs::ItilErrLog(__CLASS__,
                            0,      
                            0,      
                            ItilLogs::LVL_ERROR,
                            1,      
                            __FUNCTION__ . ": get_multi too many keys. count = " . count($keys_slice));  
                } 
                $ret_slice = $this->_mc->get_multi($keys_slice);
                $mc_info = array_merge($mc_info, $ret_slice);
            }
        }
        else
        {
            $mc_info = $this->_mc->get_multi($keys);
        }
        return $mc_info;
    }

    //分片getmulti
    private function getRedisMediaInfo($keys, $slice_num=100)
    {
        $redis_obj = ClientFactory::getRedisClient("OM_MEDIA_INFO_R");
        if ($redis_obj == false) {
            Logs::info(__FUNCTION__ . ": redis is null");
            return array();
        }

        $redis_info = array();
        $keys_count = count($keys);
        if($keys_count > $slice_num) {
            Logs::info(__FUNCTION__ . ": too much keys, need slice, num=" . $keys_count);
            for($i = 0; $i < $keys_count; $i += $slice_num) {
                $keys_slice = array_slice($keys, $i, $slice_num);
                if (count($keys_slice) > 20) {
                    Logs::info(__FUNCTION__ . ": get_multi too many keys :".json_encode($keys_slice));
                    ItilLogs::ItilErrLog(__CLASS__, 0, 0, ItilLogs::LVL_ERROR, 1, __FUNCTION__ . ": get_multi too many keys. count = " . count($keys_slice));
                }
                $ret_slice = $redis_obj->mget($keys_slice);
                $redis_info = array_merge($redis_info, $ret_slice);
            }
        } else {
            $redis_info = $redis_obj->mget($keys);
        }

        $new_redis_info = array();
        foreach($redis_info as $value) {
            if(!empty($value)) {
                $new_redis_info[] = json_decode($value, true);
            }
        }

        foreach($new_redis_info as &$rItem)
        {
            if($rItem['sicon'])
            {
                CommonFunction::replaceImgUrlToHttps($rItem['sicon']);
            }
            if($rItem['icon'])
            {
                CommonFunction::replaceImgUrlToHttps($rItem['icon']);
            }
            if($rItem['licon'])
            {
                CommonFunction::replaceImgUrlToHttps($rItem['licon']);
            }
        }

        return $new_redis_info;
    }

    /*
     * 微信文章的历史索引相关
     */
    public function getWxNewsIndex($wxids) {
        $news = array();
        $noCacheids = array();
        $indexes = $this->getWxNewsIndexFromCache($wxids, $noCacheids);
        if (!is_array($indexes) || count($noCacheids) > 0) {
            foreach ($noCacheids as $openid) {
                $tmpIndex = $this->getWxNewsIndexFromDB($openid);

                if (is_array($tmpIndex) && $tmpIndex !== false) {
                    $this->setWxNewsIndexToMc($openid, $tmpIndex);
                    Logs::info(__FUNCTION__ . ": save index to mc, openid = " . $openid);
                }

                $indexes[] = $tmpIndex;
            }
        }
        $finalIndex = array();
        foreach($indexes as $index) {
            foreach($index as $item) {
                $finalIndex[] = $item;
            }
        }
        

        return $finalIndex;
    }

    private function getWxNewsIndexFromCache($wxids, &$noCacheids=array()) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            $err = 'mc init failed!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        } else if (!is_array($wxids)|| count($wxids) <= 0) {
            $err = 'wx ids is empty!';
            Logs::info(__FUNCTION__ . ": {$err}");
            return array();
        }
        $keys = array();
        foreach ($wxids as $openid) {
            $keys[$openid] = MC_INDEX_PRE . 'wx.' . $openid;
        }
        $news_index = $mc->get_multi($keys);
        foreach ($keys as $openid => $key) {
            if (!isset($news_index[$key])) {
                Logs::info(__FUNCTION__ . ": no index in mc. key = {$key}");
                $noCacheids[] = $openid;
            }
        }
        if ($news_index == null || !is_array($news_index)) {
            Logs::info(__FUNCTION__ . ": mc return null, news_index = " . print_r($news_index, true));
            return array();
        }
        return $news_index;
    }

    private function setWxNewsIndexToMc($openid, $index) {
        $mc = ClientFactory::getMemcacheClientByName( 'NEWS_CNT' ) ;
        if ($mc == null) {
            Logs::info(__FUNCTION__ . ": mc init failed!");
            return false;
        }
        $key = MC_INDEX_PRE . 'wx.' .$openid;
        $ttl = 1800;
        $ret = $mc->set($key, $index, $ttl);
        Logs::info(__FUNCTION__ . ": save wx index to mc, ret = {$ret}, key = " . $key . ', ttl=' . $ttl);
    }

    //按openid获取表的hash值
    function getTableNumFromId($openid,$tableCount)
    {
        if(empty($openid) || $tableCount == 0) {
            return -1;
        }
        $tableNum = 0;
        $sumNum = hexdec(substr(md5($openid),0,8));
        $tableNum = $sumNum % $tableCount;
        return $tableNum;
    }

    private function getWxNewsIndexFromDB($openid) {
        $mysql_conn = ClientFactory::getMysqlClient2( 'WX_INDEX_R', 0 ) ;
        if( $mysql_conn == null ) {
            $err = ClientFactory::getError() ; 
            Logs::info( __FUNCTION__ . ": connect db faild, openid={$openid}, error={$err}" ) ;  
            return false ; // 数据库连接失败。 
        }

        $num = $this->getTableNumFromId($openid, 100);
        if($num === -1) {
            Logs::info(__FUNCTION__ . ": get table num failed!");
            return false;
        }   
        $tblName = 't_wx_news_index_' . $num ;

        $openid = str_ireplace('sleep', '', $openid);
        $openid = mysql_real_escape_string($openid, $mysql_conn);
        //$sql = "select cmsid as id, cid, timestamp, openid from {$tblName} where openid='{$openid}' order by `timestamp` desc, `id` asc limit 100" ;
        $sql = "select cmsid as id, cid, timestamp, openid from {$tblName} where openid='{$openid}' order by `timestamp` desc limit 100" ;
        Logs::info(__FUNCTION__ . ":" . $sql);
        $rs = mysql_query( $sql, $mysql_conn ) ;
        if( !$rs ) {
            Logs::info( __FUNCTION__ . ": mysql_query faild, openid={$openid}, error=" . mysql_error()  ) ;
            return false ; // 数据库执行失败。       
        }
        $rd = mysql_fetch_assoc($rs); 
        $index = array();

        $existMap = array();
        while( $rd != false ) {
            if(!isset($existMap[$rd['cid']])) {
                $existMap[$rd['cid']] = 1;
            }
            $index[] = $rd;
            $rd = mysql_fetch_assoc($rs);
        }       
        return $index;
    }

    public function getHotImagesNewsIds($chlid = 'normal', $id) {
        $mc = ClientFactory::getMemcacheClient('NEWS_CNT');
        $mc_value = $mc->get('HOT.IMAGES.INDEX.' . $chlid);

        //如果该频道在mc中没有值，则用normal
        if(empty($mc_value)) {
            $mc_value = $mc->get('HOT.IMAGES.INDEX.normal');
            if(empty($mc_value)) {
                return array();
            }
        }

        $ids = array();
        //如果频道热门图集数量大于4，则随机取出与当前文章不同的4篇
        if(count($mc_value) > 4) {
            while(count($ids) < 4) {
                $rand_key = array_rand($mc_value);
                if($mc_value[$rand_key]['id'] != $id && !in_array($mc_value[$rand_key]['id'], $ids)) {
                    $ids[] = $mc_value[$rand_key]['id'];
                }

                unset($mc_value[$rand_key]);
                //防止死循环
                if(empty($mc_value)) {
                    break;
                }
            }
        }
        else {
            foreach($mc_value as $value) {
                if($value['id'] != $id && !in_array($value['id'], $ids)) {
                    $ids[] = $value['id'];
                }
            }
        }

        //如果得到的id不足4篇，则用normal的补足4篇
        if(count($ids) < 4) {
            $normal_value = $mc->get('HOT.IMAGES.INDEX.normal');
            while(count($ids) < 4) {
                $key = array_rand($normal_value);
                if($normal_value[$key]['id'] != $id && !in_array($normal_value[$key]['id'], $ids)) {
                    $ids[] = $normal_value[$key]['id'];
                }

                unset($normal_value[$key]);
                //防止死循环
                if(empty($normal_value)) {
                    break;
                }
            }

            //防止客户端出错，如果最终不满4篇，则返回空
            if(count($ids) < 4) {
                return array();
            }
        }

        return $ids;
    }
}
