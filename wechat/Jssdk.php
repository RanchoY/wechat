<?php
namespace app\ranchoy\wechat;

use think\Db;
use think\Cookie;

class Jssdk{
    //微信公众号参数
    protected static $weChat = [];

    public function __construct($wid=''){
        if(empty($wid) && Cookie::has('wid')){
            $wid = cookie('wid');//公众号id
        }
        self::$weChat = Db::name('wechat')->where('id',$wid)->find(); 
    }

    public function getSignPackage(){
        $weChat = self::$weChat;
        $jsapiTicket = $this->getJsApiTicket();

        // 注意url一定要动态获取,不能hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=".$jsapiTicket."&noncestr=".$nonceStr."&timestamp=".$timestamp."&url=".$url;

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $weChat['appid'],
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    private function createNonceStr($length = 16) {
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $str = "";
        for ($i=0; $i<$length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket() {
        // jsapi_ticket
        $weChat = self::$weChat;
        if ($weChat['jsapi_time'] < time()) {
            $accessToken = $this->getAccessToken();
            // 如果是企业号用以下url获取ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$accessToken;
            $res = json_decode(self::httpGet($url));
            $ticket = $res->ticket;
            if($ticket){
                $weChat['jsapi_time'] = time() + 7000;
                $weChat['jsapi_ticket']  = $ticket;
                Db::name('wechat')->update($weChat);//更新公众号参数
            }
        }else{
            $ticket = $weChat['jsapi_ticket'];
        }
        return $ticket;
    }

    public function getAccessToken() {
        //查询access_token
        $weChat = self::$weChat;
        if($weChat['expire_time'] < time()){
            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=config('weixin.appid')&corpsecret=".$this->appSecret;
            $url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$weChat['appid']."&secret=".$weChat['appsecret'];
            $res = json_decode(self::httpGet($url));
            $access_token = $res->access_token;
            if ($access_token) {
                $weChat['expire_time'] = time() + 7000;
                $weChat['access_token'] = $access_token;
                Db::name('wechat')->update($weChat);//更新wechat数据
            }
        }else{
            $access_token = $weChat['access_token'];
        }
        return $access_token;
    }

    private static function httpGet($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    private static function get_php_file($filename) {
        return trim(substr(file_get_contents($filename), 15));
    }

    private static function set_php_file($filename, $content) {
        $fp = fopen($filename, "w");
        fwrite($fp, "<?php exit();?>" . $content);
        fclose($fp);
    }
}