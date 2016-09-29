<?php
/**
 * Vagex Robot 重生版
 * 做毕业设计倒没有这么多激情，倒是喜欢搞这些乱七八糟的
 * @author: horsley
 * @version: 2014-02-12
 */
date_default_timezone_set('Asia/Shanghai');
define('DEFAULT_UA', 'Mozilla/5.0 (Windows NT 6.1; rv:25.0) Gecko/20100101 Firefox/25.0');
//Log::setMinOutputLevel(Log::LEVEL_TRACE); //出问题的时候可以去掉本行注释观察调试跟踪数据

if (PHP_SAPI !== 'cli') {
    die ("This is CLI only version!");
} else {
    $v = new VagexRobot();
    $v->set_userid('388497');
    $v->set_youtube_email('liyuan.leon@gmail.com');

    //下面这个方法可以手工指定youtube用户名，官方限制最多10个用户名还限了喜欢和收藏的数量
    //程序原本设计会自动随机生成一个用户名，有时候如果程序死掉重启什么的，会导致使用了很多新的用户名，超出限制
    //这时候可以用下面这个手工指定用户名的方式重用那些还没到限制的用户名
    //$v->set_youtube_username('Leonn');

    //国内环境需要挂代理才能正确取得youtube信息，注意这个代理设置是全局的，也被用在vagex提交和获取
    //$v->set_proxy('127.0.0.1:8888', false);

    //国内环境想要正确取得youtube信息也可以考虑使用china mode，即：
    //部署一个小脚本video_info.php 到国外的sass pass 虚拟主机 等，专门用于获取youtube信息
    //$v->set_youtube_proxy('http://abc.com/video_info.php');
    $v->run();
}

/**
 * Class VagexRobot
 */
class VagexRobot {
    const VAGEX_URL_A = 'http://vagex.com/ffupdater151a.php';
    const VAGEX_URL_B = 'http://vagex.com/ffupdater151b.php';
    const VAGEX_URL_E = 'http://vagex.com/ffupdater151e.php';

    const VAGEX_SPR_SID = 'SID:::|';
    const VAGEX_SPR_EOF = ':::<br>';
    const VAGEX_SPR_EOL = '::||::<br>';
    const VAGEX_SPR_VNO = ':::';
    const VAGEX_SPR_FLD = '|:|';
    const VAGEX_RE_ERR = '/\:\|(.*?)\|\:/';
    const VAGEX_RE_EAR = '/\:\:\|\|(.*?)\|\|\:\:/';

    private $sleep_time;
    private $data_default = array(
        'userid'        => '0',
        'build'         => '20131112160018',
        'ua'            => DEFAULT_UA,
        'versid'        => '1.6.4',
        'ffversion'     => '25.0.1',
        'safemode'      => 'false',
        'os'            => 'Windows NT 6.1',
        'email'         => 'abc@gmail.com',
        'username'      => 'username_catching_error',
        'chk_runtime'   => 'true',
        'flash'         => 'true',
        'html5'         => 'true'
    );

    private $data_dynamic = array();

    function __construct() {
        Log::info("Vagex Cheater instance initialized");
    }

    function set_userid($uid) {
        $this->data_default['userid'] = $uid;
        Log::info("Set user id: " . $uid);
    }

    function set_proxy($proxy, $is_sock5 = false) {
        Curl::setProxy($proxy, $is_sock5);
        Log::info("Set proxy: " . ($is_sock5?'sock5://':'') . $proxy );
    }

    function set_youtube_email($email) {
        $this->data_default['email'] = $email;
        Log::info("Set youtube email: " . $email);
    }

    function set_youtube_username($name) {
        $this->data_default['username'] = $name;
        Log::info("Set youtube username: " . $name);
    }

    /**
     * china mode
     * 设置一个代理用于获取youtube信息，使得本robot可以在国内运行
     */
    function set_youtube_proxy($url) {
        $this->data_default['youtube_proxy'] = $url;
        Log::info("Set youtube proxy: " . $url . ' (China Mode)');
    }

    function run() {
        Log::info('Start to run main routine');
        while(true) {
            Log::info("A new loop of a video array start");
            if($this->update_video_arr()) {
                foreach($this->data_dynamic['video_arr'] as $vc_item) {
                    Log::info('Deal with item:' . $vc_item[1][0]);
                    Log::debug(json_encode($vc_item));

                    //sleep time 官方测算应该是随机在5~20硬延迟（不算小的几百毫秒的延迟执行和代码运行时间、页面加载时间
                    //这里人为适度降延迟上限，得分更快，但是下限不敢乱动，因为太快官方是会不认的
                    $this->sleep_time = intval($vc_item[1][1]) + mt_rand(5, 7);
                    Log::info('Let\'s sleep for ' . $this->sleep_time . ' seconds');
                    sleep($this->sleep_time);
                    Log::info('Wake up, report processed');

                    if($result = $this->report_processed($vc_item[0])) {
                        if (strlen($result) < 2) {
                            Log::info('Earnt: ' . $result);
                        } else {
                            Log::info('Fail: ' . $result);
                        }
                    } else {
                        Log::info('Fail: ' . $result);
                    }
                }
            } else {
                Log::warn('fail update video array, sleep 20 seconds');
                sleep(20);
            }
        }
    }

    /**
     * get video items
     * @return bool
     */
    function update_video_arr() {
        Log::info('Requesting new Show Array.');
        $resp = Curl::post(self::VAGEX_URL_A, http_build_query(array(
            'userid' => $this->data_default['userid'],
            'ua' => $this->data_default['ua'],
            'build' => $this->data_default['build'],
            'versid' => $this->data_default['versid']
        )));

        $_ = explode(self::VAGEX_SPR_SID, $resp);
        if (count($_) != 2) {
            Log::error("Cut Show Array Failed");
            Log::debug($resp);
            return false;
        }

        Log::info('Show Array Request Data Received...');

        $this->data_dynamic['sid'] = array_shift(explode(self::VAGEX_SPR_EOF, $_[1]));
        $this->data_dynamic['video_arr'] = explode(self::VAGEX_SPR_EOL, $_[0]);
        if (empty($this->data_dynamic['video_arr'][count($this->data_dynamic['video_arr']) - 1])) {
            unset($this->data_dynamic['video_arr'][count($this->data_dynamic['video_arr']) - 1]);
        }

        foreach($this->data_dynamic['video_arr'] as &$v) {
            if (empty($v)) continue;
            $e = explode(self::VAGEX_SPR_VNO, $v); //$e[0] = video_no
            $v = array($e[0]);
            $v[] = explode(self::VAGEX_SPR_FLD, $e[1]);
        }
        Log::info('Show Array parse end, array count: ' . count($this->data_dynamic['video_arr']));
        return true;
    }

    /**
     * report process
     * @param $video_no
     * @return bool|int
     */
    function report_processed($video_no) {
        Log::info('report_processed start');
        $PostData = $this->make_report_data($video_no);
        $PostDataStr = base64_encode(http_build_query($PostData));
        Log::debug('postData:'.$PostDataStr);

        if ($response_body = Curl::post(self::VAGEX_URL_B, http_build_query(array('data' => $PostDataStr)))) {
            //提取错误信息
            preg_match(self::VAGEX_RE_ERR, $response_body, $match);
            $err_msg = isset($match[1])?$match[1]:false;
            if (!empty($err_msg)) {
                Log::warn('sever return error msg:'.$err_msg);
                if(substr($err_msg, 0, 16) == 'YTUser done over') { //youtube username limit exceed
                    Log::info('youtube username limit exceed, try to generate another');
                    $this->generate_random_ytusername();
                }
            }

            //提取赚取点数信息
            preg_match(self::VAGEX_RE_EAR, $response_body, $match);
            return isset($match[1])?$match[1]:false;
        } else {
            Log::warn('report_processed Failed');
            return false;
        }
    }

    /**
     * Wrap all data that need to post
     * @param $video_no
     * @return array
     */
    function make_report_data($video_no) {
        Log::trace('make_report_data start');
        $this->get_dynamic_data($video_no); //视频相关的动态数据获取
        $this->make_fake_data();            //各种随机瞎编数据的生成

        return array( //这里的顺序按照官方来
            'userid'        => $this->data_default['userid'],
            'versid'        => $this->data_default['versid'],
            'ffversion'     => $this->data_default['ffversion'],
            'safemode'      => $this->data_default['safemode'],
            'os'            => urlencode($this->data_default['os']),
            'vgxsid'        => urlencode($this->data_dynamic['sid']),
            'url'           => urlencode($this->data_dynamic['url']),
            'length'        => $this->data_dynamic['length'],
            'exactTime'     => $this->data_dynamic['exactTime'],
            'email'         => urlencode($this->data_default['email']),
            'username'      => urlencode($this->data_default['username']),
            'watcheduser'   => urlencode($this->data_dynamic['watcheduser']),
            'liked'         => $this->data_dynamic['liked'] == 1 ? 'true' : 'false',
            'subed'         => $this->data_dynamic['subed'] == 1 ? 'true' : 'false',
            'siteid'        => $this->data_dynamic['siteid'],
            'nv'            => $video_no,
            'nc'            => $this->data_dynamic['nc'],
            'chk_runtime'   => $this->data_default['chk_runtime'],
            'flash'         => $this->data_default['flash'],
            'pageData'      => urlencode($this->data_dynamic['pageData']),
            'machine'       => urlencode($this->data_dynamic['machine']),
            'html5'         => $this->data_default['html5'],
            'duration'      => $this->data_dynamic['duration'],
            'currTime'      => $this->data_dynamic['currTime'],
            'speed'         => $this->data_dynamic['speed'],
            'ts'            => urlencode($this->data_dynamic['ts']),
        );
    }

    /**
     * get dynamic data from play page
     * @param $video_no
     */
    function get_dynamic_data($video_no) {
        if (!empty($this->data_default['youtube_proxy'])) { //china mode
            if ($video_info = Curl::get($this->data_default['youtube_proxy'] . '?id=' . $this->get_vid($video_no))) {
                $video_info = json_decode($video_info, true);
                if (!isset($video_info['error']) || $video_info['error'] == true) {
                    Log::error('Video info proxy report error: '.(empty($video_info['error'])?'':$video_info['error']));
                } else {
                    $this->data_dynamic['watcheduser']  = $video_info['data']['watcheduser'];
                    $this->data_dynamic['pageData']     = $video_info['data']['pageData'];
                    if (empty($this->data_dynamic['machine'])) {
                        //这个机器id由youtube播放页返回的cookie中提取，如果是已经登录，值应该一直是同一个
                        $this->data_dynamic['machine']  = $video_info['data']['machine'];
                    }
                    $this->data_dynamic['exactTime']    = $video_info['data']['exactTime'];
                }
            } else {
                Log::error('Get video info from '.$this->data_default['youtube_proxy'].'fail!');
            }
        } else {
            $play_page_body = Curl::get($this->get_video_url($video_no), $play_page_header);

            $this->data_dynamic['watcheduser'] = self::get_watched_userid($play_page_body);
            $this->data_dynamic['pageData'] = self::get_page_title($play_page_body);
            if (empty($this->data_dynamic['machine'])) {
                //这个机器id由youtube播放页返回的cookie中提取，如果是已经登录，值应该一直是同一个
                $this->data_dynamic['machine'] =  self::get_visitor_id($play_page_header);
            }
            $this->data_dynamic['exactTime'] = self::get_video_length($play_page_body);
        }

        $this->data_dynamic['url'] = $this->data_dynamic['video_arr'][$video_no][1][0];
        $this->data_dynamic['liked'] = $this->data_dynamic['video_arr'][$video_no][1][3];
        $this->data_dynamic['subed'] = $this->data_dynamic['video_arr'][$video_no][1][4];
        $this->data_dynamic['siteid'] = $this->data_dynamic['video_arr'][$video_no][1][2];

        $this->data_dynamic['nv'] = $video_no;
        $this->data_dynamic['nc'] = !isset($this->data_dynamic['nc']) ? 0 : ($this->data_dynamic['nc']+1);
    }

    /**
     * Make fake data base on random numbers or previous data
     */
    function make_fake_data () {
        $this->data_dynamic['duration']     = $this->data_dynamic['exactTime'] + lcg_value();
        $this->data_dynamic['speed']        = mt_rand(140000, 200000) + lcg_value();
        $this->data_dynamic['length']       = $this->sleep_time + mt_rand(1, 2);
        $this->data_dynamic['currTime']     = $this->sleep_time + mt_rand(1, 2);

        if ($this->data_default['username'] == 'username_catching_error') $this->generate_random_ytusername();

        //下面这些速度监控相关的 都是随机瞎编的数据，根据多次测量的经验取值范围
        $new_speed = mt_rand(60, 69) + lcg_value();
        if (!isset($this->data_dynamic['min_speed']) || $new_speed < $this->data_dynamic['min_speed']) {
            $this->data_dynamic['min_speed'] = $new_speed;
        }

        $new_speed = mt_rand(250000, 470000) + lcg_value();
        if (!isset($this->data_dynamic['max_speed']) || $new_speed > $this->data_dynamic['max_speed']) {
            $this->data_dynamic['max_speed'] = $new_speed;
        }

        $rand_time = array(
            mt_rand(ceil($this->data_dynamic['min_speed']), floor($this->data_dynamic['max_speed'])) + lcg_value(),
            mt_rand(9000, 18200) + lcg_value(),
            mt_rand(16000, 21000) + lcg_value(),
            mt_rand(55000, 90000) + lcg_value(),
        );

        $this->data_dynamic['ts'] = sprintf('%f:%f:%f:%f:%f:%f',
            $this->data_dynamic['min_speed'],
            $this->data_dynamic['max_speed'],
            $rand_time[0], $rand_time[1], $rand_time[2], $rand_time[3]
        );
    }

    /**
     * 随机生成一个youtube 用户名
     * using default username or catching error leads to like and sub not counting!!
     */
    function generate_random_ytusername() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        $this->data_default['username'] = $randomString;
        Log::info('generate_random_ytusername:'.$randomString);
    }

    /**
     * make full youtube url by vid
     * @param $video_no
     * @return string
     */
    function get_video_url($video_no) {
        return 'http://www.youtube.com/watch?v=' . $this->get_vid($video_no);
    }

    /**
     * Get Show item's youtube vid
     * @param $video_no
     * @return mixed
     */
    function get_vid($video_no) {
        return $this->data_dynamic['video_arr'][$video_no][1][0];
    }

    /**
     * Preg find page title from html
     * @param $html
     * @return mixed
     */
    static function get_page_title($html) {
        preg_match('/<title>(.*?)<\/title>/', $html, $match);
        return isset($match[1])?$match[1]:false;
    }

    /**
     * Preg Youtube visitor id from response cookie
     * @param $head
     * @return mixed
     */
    static function get_visitor_id($head) {
        preg_match('/VISITOR_INFO1_LIVE=(.*?);/', $head, $match);
        return isset($match[1])?$match[1]:false;
    }

    /**
     * Preg Youtube video owner id from html
     * @param $html
     * @return mixed
     */
    static function get_watched_userid($html) {
        preg_match('/yt-uix-sessionlink yt-user-videos.*\/user\/(.*?)\//', $html, $match);
        return isset($match[1])?$match[1]:false;
    }

    /**
     * Preg Youtube video duration
     * @param $html
     * @return mixed
     */
    static function get_video_length($html) {
        preg_match('/"length_seconds":=\s+(\d+),/', $html, $match);
        return isset($match[1])?$match[1]:false;
    }

}
/////////////////////////////////////////////////////////////////////////////////
class Curl {
    private static $_opt = array();

    /**
     * 简单的get请求
     * @param $url
     * @param string $header 可选返回header
     * @return bool
     */
    public static function get($url, &$header = '') {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => DEFAULT_UA,
        ) + self::$_opt);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        $response = explode("\r\n\r\n", $response, 2);
        $rsp_body = $response[1]; //返回Body
        $header = $response[0];
        curl_close($ch);

        return $rsp_body;
    }


    /**
     * 简单的post提交
     * @param $url
     * @param $data
     * @return bool|mixed
     */
    public static function post($url, $data) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => DEFAULT_UA,
        ) + self::$_opt);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        curl_close($ch);
        return $response;
    }

    /**
     * 代理设置
     * @param $proxy_str
     * @param bool $is_socks5 是否socks5，可空默认假
     */
    public static function setProxy($proxy_str, $is_socks5 = false) {
        self::$_opt[CURLOPT_PROXY] = $proxy_str;
        if ($is_socks5) {
            self::$_opt[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        }
    }
}
/////////////////////////////////////////////////////////////////////////////////
class Log {
    /**
     * 日志等级
     */
    const LEVEL_TRACE = 1;
    const LEVEL_DEBUG = 2;
    const LEVEL_INFO  = 3;
    const LEVEL_WARN  = 4;
    const LEVEL_ERROR = 5;
    const LEVEL_FATAL = 6;

    const TIME_FORMAT = '[Y/m/d H:i:s] ';

    private static $level_pfx = array(
        self::LEVEL_TRACE => '[TRACE] ',
        self::LEVEL_DEBUG => '[DEBUG] ',
        self::LEVEL_INFO  => '[INFO] ',
        self::LEVEL_WARN  => '[WARN] ',
        self::LEVEL_ERROR => '[ERROR] ',
        self::LEVEL_FATAL => '[FATAL] ',
    );

    private static $min_output_level = self::LEVEL_INFO;

    private static function write($content, $level) {
        if ($level < self::$min_output_level) return; //等级不够不输出
        $log_line = date(self::TIME_FORMAT);
        $log_line .= (isset(self::$level_pfx[$level])?self::$level_pfx[$level]:'');
        $log_line .= $content.PHP_EOL;
        echo $log_line;
    }

    /**
     * 分等级日志
     * @param $content
     */
    public static function trace($content) { self::write($content, self::LEVEL_TRACE); }
    public static function debug($content) { self::write($content, self::LEVEL_DEBUG); }
    public static function info($content)  { self::write($content, self::LEVEL_INFO); }
    public static function warn($content)  { self::write($content, self::LEVEL_WARN); }
    public static function error($content) { self::write($content, self::LEVEL_ERROR); }
    public static function fatal($content) { self::write($content, self::LEVEL_FATAL); }

    /**
     * 设置输出日志等级
     * @param $level
     */
    public static function setMinOutputLevel($level) {
        self::$min_output_level = $level;
    }
}