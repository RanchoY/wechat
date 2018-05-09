<?php
namespace app\ranchoy\wechat;

use think\Db;
use think\Cookie;
use think\Session;

class Wechat{ 
    //微信公众号参数
    protected static $weChat = [];

    public function __construct($wid=''){
        if( empty($wid) && Cookie::has('wid') ){
            $wid = cookie('wid');//公众号id
        }
        self::$weChat = Db::name('wechat')->where('id',$wid)->find(); 
    }

    //授权获得个人微信信息,并cookie
    public function person(){
        $weChat = self::$weChat;
        $data = input('code');
        $request = request();
        $url = $request->domain().$request->url();//重新跳转的url
        if(!empty($data)){
            $get_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$weChat['appid']."&secret=".$weChat['appsecret']."&code=".$data."&grant_type=authorization_code";
            $result = self::get($get_token_url);
            if(empty($result['access_token'])){
                echo "<script>window.location.href='https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weChat['appid']."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect'</script>";
                exit();
            }
            $get_info_url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$result['access_token']."&openid=".$result['openid']."&lang=zh_CN";
            $userInfo = self::get($get_info_url);
            Cookie::set('openid',$userInfo['openid'],360000); //cookie保存openid一个周
            Cookie::set('unionid',$userInfo['unionid'],360000); //cookie保存openid一个周
            session('person',$userInfo);  //以session形式返回用户信息
            echo "<script>window.location.href='".$redirect_url."'</script>";
        }else{
            echo "<script>window.location.href='https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weChat['appid']."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect'</script>";
        }
    }

    //静默获取个人openid,并cookie
    public function openid(){
        $weChat = self::$weChat;
        $data = input('code');
        $request = request(); 
        $url = $request->domain().$request->url();
        if(!empty($data)){
            $get_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$weChat['appid']."&secret=".$weChat['appsecret']."&code=".$data."&grant_type=authorization_code";
            $result = self::get($get_token_url);
            if(isset($result['openid'])){
                Cookie::set('openid',$result['openid'],360000);  //cookie保存openid一个周
            }
            echo "<script>window.location.href='".$url."'</script>";
        }else{
            echo "<script>window.location.href='https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weChat['appid']."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect'</script>";
        }
    }

    //静默获取个人unionid,并cookie
    public function unionid(){
        $weChat = self::$weChat;
        $access_token = $this->getAccessToken();
        $data = input('code');
        $request = request();
        $url = $request->domain().$request->url();
        if(!empty($data)){
            $openid = cookie('openid');
            $get_token_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
            $result = self::get($get_token_url);
            if(isset($result['subscribe']) && ($result['subscribe']==1) ){
                Session::set('userinfo',$result);//获取unionid获取到的信息
                Cookie::set('unionid',$result['unionid'],360000);  //COOKIE保存unionid一个周
            }else{
                //未关注公众号跳转关注页面
                echo "<script>window.location.href='".url('index/wechat/follow')."'</script>";
            }
            echo "<script>window.location.href='".$url."'</script>";
        }else{
            echo "<script>window.location.href='https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weChat['appid']."&redirect_uri=".urlencode($url)."&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect'</script>";
        }
        
    }

    //发送模板消息
    public function mb($template_id,$fromUsername,$title,$last,$url,$value){
        $access_token = $this->getAccessToken();
        $post_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;
        $arr = array();
        $arr['touser'] = $fromUsername;
        $arr['template_id'] = $template_id;
        $arr['url'] = $url;
        $arr['data']['first']['value'] = $title;
        $arr['data']['first']['color'] = "#173177";
        foreach($value as $key => $val){
            $keyword = 'keyword'.($key+1);
            $arr['data'][$keyword]['value'] = $val;
            $arr['data'][$keyword]['color'] = "#173177";
        }
        $arr['data']['remark']['value'] = $last;
        $arr['data']['remark']['color'] = "#173177";

        $data=json_encode($arr);
        self::post($post_url,$data);
    }

    //群发送模板消息
    public function mb_all($template_id,$title,$last,$url,$value){
        $access_token = $this->getAccessToken();
        $post_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$access_token;
        $arr = array();
        $arr['template_id'] = $template_id;
        $arr['url'] = $url;
        $arr['data']['first']['value'] = $title;
        $arr['data']['first']['color'] = "#173177";
        foreach($value as $key => $val){
        $keyword = 'keyword'.($key+1);
        $arr['data'][$keyword]['value'] = $val;
        $arr['data'][$keyword]['color'] = "#173177";
        }
        $arr['data']['remark']['value'] = $last;
        $arr['data']['remark']['color'] = "#173177";

        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$access_token."&next_openid=";
        $all_openid = self::get($url);
        foreach($all_openid['data']['openid'] as $key => $openid){
            $arr['touser']=$openid;
            $data = json_encode($arr);
            self::post($post_url,$data);
        }
    }

    //获取参数二维码,场景值为字符串类型,长度限制为1到64
    public function p_code_str($str){
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$this->getAccessToken();
        $arr = json_encode(array('action_name'=>'QR_LIMIT_STR_SCENE','action_info'=>array('scene'=>array('scene_str'=>$str))));
        $result = json_decode(self::post($url,$arr));
        if(isset($result->ticket)){
            return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$result->ticket;
        }
    }
    
    //获得全局access_token
    public function getAccessToken(){
        $weChat = self::$weChat;
        if($weChat['expire_time'] < time()){
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=". $weChat['appid'] ."&secret=". $weChat['appsecret'];
            $result = self::get($url);
            $access_token = $result['access_token'];
            if($access_token){
                $weChat['expire_time'] = time() + 7000;
                $weChat['access_token'] = $access_token;
                Db::name('wechat')->update($weChat);//更新wechat数据
            }
        }else{
            $access_token = $weChat['access_token'];
        }
        return $access_token;
    }

    //post方法
    private static function post($url, $data){
        $opts = array('http' => array('method'=>'POST', 'header'=>'Content-type:application/x-www-form-urlencoded', 'content'=>$data));
        $context = stream_context_create($opts);
        return $result = file_get_contents($url, false, $context);
    }

    //get方法
    private static function get($url){
        $ch = curl_init();
        curl_setopt($ch , CURLOPT_URL, $url);
        curl_setopt($ch , CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close( $ch );
        $arr = json_decode($res, true);
        return($arr);
    }

    private static function get_php_file($filename) {
        return trim(substr(file_get_contents($filename), 15));
    }

    private static function set_php_file($filename, $content) {
        $fp = fopen($filename, "w");
        fwrite($fp, "<?php exit();?>".$content);
        fclose($fp);
    }
}