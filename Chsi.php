<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/24
 * Time: 16:19
 */

namespace app\api\controller;

use think\Controller;
use think\Db;


class Chsi extends Controller
{

    protected  $_eventId    = 'submit';
    protected  $_submit     = '登  录';
    protected  $_ip         = '';
    protected  $_taskId     = '';
    protected  $_apiKey     = '';



    // 创建接口使用用户key

    function createApiKey(){
        $phone = input('param.phone','','trim');

        if(empty($phone)){
            return json_encode([
                'code'     =>  500
                 'msg'      =>  '手机号码异常',
             ]);
         }

        $res = Db::table('snake_chsi_user')->where('username',$phone)->find();

        if(empty($res)){
            return json_encode([
                'code'     =>  500,
                'msg'      =>  '用户不存在',
            ]);
        }

        if($res['apikey']){
            return json_encode([
                'code'     =>  200,
                'msg'      =>  '当前账户已生成应用秘钥',
            ]);
        }


        $apiKey = md5(base64_encode($phone.$res['id'].$res['create_time']));

        $return = Db::table('snake_chsi_user')->where('username',$phone)->update([
            'apikey'       => $apiKey,
            'can_use_num'  => 10
        ]);

        if($return !== false){
            return json_encode([
                'code'     =>  200,
                'msg'      =>  '生成应用秘钥成功',
            ]);
        }
    }


    /*
     * 接口创建任务
     * @return task_id
     * */
    function createTask()
    {

        $this->_apiKey = input('param.apiKey','','trim');

        if(empty($this->_apiKey)){
            return json_encode([
                'code'          =>  500,
                'msg'           =>  '任务创建失败,检查apiKey',
                'apiKey'        =>  $this->_apiKey,
            ]);
        }


        $apiKey_exits = Db::table('snake_chsi_user')->where('apikey',$this->_apiKey)->find();

        if(empty($apiKey_exits)){
            return json_encode([
                'code'          =>  500,
                'msg'           =>  '任务创建失败,apiKey不存在',
                'apiKey'        =>  $this->_apiKey,
            ]);
        }

        if($apiKey_exits['can_use_num'] <= $apiKey_exits['use_num']){
            return json_encode([
                'code'          =>  500,
                'msg'           =>  '任务创建失败,api可调用次数不足',
                'apiKey'        =>  $this->_apiKey,
            ]);
        }


        //创建任务ID  并存储ID
        $this->_taskId    = md5(uniqid() . rand(1111,9999));
        $createTime = time();

        Db::table('snake_chsi_api_log')->insert([
            'taskId'        =>  $this->_taskId,
            'createTime'    =>  $createTime,
            'queryUser'     =>  $this->_apiKey
        ]);

        return json_encode([
            'code'          =>  200,
            'msg'           =>  '任务创建成功',
            'taskId'        =>  $this->_taskId,
            'apiUrl'        =>  'chsiFirst'
        ]);

    }

    /*
     * 学信网第一步
     * 首先登录页面获取 lt  action  判断是否需要验证码
     *  如果不需要验证码 提交 lt 用户名 密码
     *
     *
     * */

    function chsiFirst()
    {
        //任务ID
        $this->_taskId      =   input('param.taskId','','trim');
        $userName           =   input('param.userName','','trim');
        $passWord           =   input('param.passWord','','trim');

        if(empty($userName) || empty($passWord)){
            return json_encode([
                'code'  =>  500,
                'msg'   =>  '账号密码不能为空'
            ]);
        }

        //检查任务ID是否存在

        $taskIdExits = $this->checkTaskIdExits($this->_taskId);

        if($taskIdExits['code'] == 500){
            return json_encode([
                'code'  =>  $taskIdExits['code'],
                'msg'   =>  $taskIdExits['msg']
            ]);
        }else{
            //更新任务的被查询用户账号以及密码
            $this->updateTask(
                [
                    'taskId'    =>  $this->_taskId
                ],
                [
                    'userName'  =>  $userName,
                    'passWord'  =>  $passWord
                ]
            );
        }

        //生成随机ip

        $this->_ip = $this->yeildIp();

        $header = [
            'Host:account.chsi.com.cn',
            'Origin:https://account.chsi.com.cn',
            'Referer:https://account.chsi.com.cn/passport/login',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
            'Host:account.chsi.com.cn',
            'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip
        ];

        //模拟进入页面

        $res_ = $this->http_query('https://account.chsi.com.cn/passport/login',[],$header,'',$this->_taskId);


        //获取第一次爬虫的相关参数

        $return  = $this->queryRegix($res_['data'],$res_['cookiePath'],$this->_taskId);

        //需要验证码

        if($return['captcha'] == 'captcha'){

            $imgUrl = $this->getCaptcha($this->_taskId);
            //echo  "<img src='data:image/jpeg;base64,{$imgUrl['imgUrl']}'>";
            return json_encode([
                'code'      => 200,
                'msg'       => '需要验证码',
                'imgUrl'    => $imgUrl['imgUrl'],
                'apiUrl'    => 'chsiLogin'
            ]);

        }else{

            //不需要验证码  则直接去登陆
            return json_encode([
                'code'      => 200,
                'msg'       => '不需要验证码',
                'imgUrl'    => '',
                'apiUrl'    => 'chsiLogin'
            ]);

        }

    }


    /*
    * 提交验证码并登录
    *
    * */

    public function chsiLogin()
    {
        $this->_taskId = input('param.taskId','','trim');
        $yzm = input('param.captcha','','trim');

        //检查任务ID是否存在

        $taskIdExits = $this->checkTaskIdExits($this->_taskId);

        if($taskIdExits['code'] == 500){
            return [
                'code'  =>  $taskIdExits['code'],
                'msg'   =>  $taskIdExits['msg']
            ];
        }else{
            $cookiePath = $taskIdExits['paramVal']['cookiePath'] ;
            $lt         = $taskIdExits['paramVal']['lt'] ;
            $username   = $taskIdExits['userName'];
            $password   = $taskIdExits['passWord'];
        }


        $header = [
            'Host:account.chsi.com.cn',
            'Origin:https://account.chsi.com.cn',
            'Referer:https://account.chsi.com.cn/passport/login',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
            'Host:account.chsi.com.cn',
            'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip
        ];


        if(empty($yzm)){

            $postData = http_build_query(
                [
                    'lt'        => $lt,
                    '_eventId'  => $this->_eventId,
                    'submit'    => $this->_submit,
                    'username'  => $username,
                    'password'  => $password,
                ]
            );

        }else{
            $postData = http_build_query(
                [
                    'lt'        => $lt,
                    '_eventId'  => $this->_eventId,
                    'submit'    => $this->_submit,
                    'username'  => $username,
                    'password'  => $password,
                    'captcha'   => $yzm
                ]
            );

        }

        $res_ = $this->http_query(
            'https://account.chsi.com.cn' . session('action'),
            $postData,
            $header,
            $cookiePath,
            $this->_taskId
        );


        \phpQuery::newDocument($res_['data']);

        $pwdError = pq("#status")->text();

        if(trim($pwdError) == '您输入的用户名或密码有误。'){
            return json_encode([
                'code'   => 500,
                'msg'    => '您输入的用户名或密码有误',
                'apiUrl' => 'chsiFirst',
                'data'   => []
            ]);
        }

        $errMsg      =   pq(".errors")->text();

        if($errMsg == '图片验证码输入有误'){
            return json_encode([
                'code'   => 500,
                'msg'    => '图片验证码错误,请重新初始化获取验证码',
                'apiUrl' => 'chsiFirst',
                'data'   => []
            ]);

        }

        if(strrpos($res_['data'],'302')){

            $res = $this->getContent($this->_taskId);
            //echo  "<img src='data:image/jpeg;base64,{$res['data']['cpic']}'>";

            //存储临时的内容图片到本地
            if(!file_exists('upload' .  DS . 'storgecont')
            ){
                mkdir('upload' .  DS . 'storgecont' ,0777);
            }
            $imgurl = 'upload' .  DS . 'storgecont' . DS . uniqid() . rand(1000,9999) . '.jpg';

            file_put_contents($imgurl,base64_decode($res['data']['cpic']));


            $fileName = md5(base64_encode(uniqid() . rand(1000,9999))) . '.jpg';

            file_put_contents($imgurl,base64_decode($res['data']['cpic']));

            $this->uploadQiniu($fileName, $imgurl);

            unlink($imgurl);

            $qcr_res = $this->qcr_data('http://ozhljqscj.bkt.clouddn.com/'.$fileName);



            $qcr_res['_id']['$oid'] = $this->_taskId;
            $qcr_res['schoolRoll']['admissionPhoto'] = $res['data']['lpic'];
            $qcr_res['schoolRoll']['graduationPhoto'] = $res['data']['xpic'];
            $qcr_res['schoolRoll']['rawImg'] = $res['data']['cpic'];

            return json_encode([
                'code'                  =>  $res['code'],
                'msg'                   =>  $res['msg'],
                'data'                  =>  $qcr_res
            ]);


        }

    }




    /*
     * qcr  test
     * 腾讯
     * */

    public function qcr_data($image_url = ''){
        $url = 'https://ai.qq.com/cgi-bin/appdemo_generalocr?g_tk=5381';

        //$data_url = 'http://' . $_SERVER['HTTP_HOST'] . DS . $image_url ;

        $postData = [
            'image_url' => $image_url/*'http://ozur0kva7.bkt.clouddn.com/show.jpg'*/
        ];

        $res = $this->http_curl_qcr($url,$postData);

        $return = json_decode($res,true);


        $parse_data = $return['data']['item_list'];


        $res_data = [
            '_id' =>  [
                '$oid'    => ''
            ]
            ,
            'createTime' => date('Y-m-d H:i:s')
            ,
            'educationalBackground' => []
            ,
            'hasRawImg' =>  true
            ,
            'loginName' =>  ''
            ,
            'schoolRoll' => [
                'admissionDate' => str_replace(' ','',$parse_data[29]['itemstring'])
                ,
                'admissionPhoto' => ''
                ,
                'birthDate' => $parse_data[7]['itemstring']
                ,
                'branch' => $parse_data[20]['itemstring']
                ,
                'certificateNumber' => str_replace(' ','',$parse_data[11]['itemstring'])
                ,
                'class' => $parse_data[26]['itemstring']
                ,
                'degreeCategory' => $parse_data[12]['itemstring']
                ,
                'department' => ''
                ,
                'educationLevel' => $parse_data[16]['itemstring']
                ,
                'educationalSystem' => $parse_data[19]['itemstring']
                ,
                'graduationPhoto' => ''
                ,
                'learningForm' => $parse_data[21]['itemstring']
                ,
                'leaveDate' => $parse_data[33]['itemstring']
                ,
                'name' => $parse_data[3]['itemstring']
                ,
                'nations' => $parse_data[6]['itemstring']
                ,
                'rawImg' => ''
                ,
                'school' =>  $parse_data[10]['itemstring']
                ,
                'sex' =>  $parse_data[1]['itemstring']
                ,
                'specialities' =>  $parse_data[14]['itemstring']
                ,
                'status' =>  $parse_data[31]['itemstring']
                ,
                'studentLD' =>  str_replace(' ','',$parse_data[30]['itemstring'])
            ]


        ];

        return $res_data;
    }


    /*
     * 图片qcr识别
     * */
    public function http_curl_qcr($url,$postData = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //location 相关操作
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        if($postData){
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $res = curl_exec($curl);

        curl_close ($curl);

        return $res;
    }




    /*
     * 需要验证码
     * */
    public function getCaptcha($taskId)
    {
        //$taskId = input('param.taskId','','trim');
        //检查任务ID是否存在
        $this->_taskId = $taskId;

        $taskIdExits = $this->checkTaskIdExits($this->_taskId);

        if($taskIdExits['code'] == 500){
            return [
                'code'  =>  $taskIdExits['code'],
                'msg'   =>  $taskIdExits['msg']
            ];
        }else{
            $cookiePath = $taskIdExits['paramVal']['cookiePath'] ;
        }

        $header = [
            'Host:account.chsi.com.cn',
            'Origin:https://account.chsi.com.cn',
            'Referer:https://account.chsi.com.cn'.session('action').'?service=https%3A%2F%2Fmy.chsi.com.cn%2Farchive%2Fj_spring_cas_security_check',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
            'Host:account.chsi.com.cn',
            'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip
        ];

        $id = mt_rand(1000,9999).'.'.mt_rand(1000000,9999999).mt_rand(100000,999999);

        $captcha_parse = $this->http_query_captcha(
            'https://account.chsi.com.cn/passport/captcha.image?id='. $id,
            $header,
            $cookiePath,
            [
                'where' =>  ['taskId' => $this->_taskId],
                'update'=>  $taskIdExits['paramVal']
            ]
        );


        return [
            'code'      => 200,
            'msg'       => '验证码',
            'imgUrl'    => $captcha_parse['data'],
            'apiUrl'    => 'chsiLogin'
        ];

    }


    public function getContent($taskId)
    {
        $this->_taskId = $taskId;

        //检查任务ID是否存在

        $taskIdExits = $this->checkTaskIdExits($taskId);

        if($taskIdExits['code'] == 500){
            return [
                'code'  =>  $taskIdExits['code'],
                'msg'   =>  $taskIdExits['msg']
            ];
        }else{
            $cookiePath = $taskIdExits['paramVal']['cookiePath'] ;
        }


        $this->_ip = $this->yeildIp();

        $header_one = [
            'Host:account.chsi.com.cn',
            'Referer:https://account.chsi.com.cn/passport/login' . session('action') . '?service=https%3A%2F%2Fmy.chsi.com.cn%2Farchive%2Fj_spring_cas_security_check',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36', 'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip,
            'CLIENT-IP:' . $this->_ip,
            'X-Requested-With:XMLHttpRequest'
        ];

        //在进入 学信档案页面时   host 很重要    地址http://my.chsi.com.cn/archive/index.action
        $header_two = [
            'Host: my.chsi.com.cn',
            'Referer:https://account.chsi.com.cn/passport/login' . session('action') . '?service=https%3A%2F%2Fmy.chsi.com.cn%2Farchive%2Fj_spring_cas_security_check',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36', 'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip,
            'CLIENT-IP:' . $this->_ip,
            'X-Requested-With:XMLHttpRequest'
        ];

        //查看最终数据报告页面

        $header_three = [
            'Host: my.chsi.com.cn',
            'Referer: https://my.chsi.com.cn/archive/index.action',
            'Upgrade-Insecure-Requests:1',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36', 'CLIENT-IP:' . $this->_ip,
            'X-FORWARDED-FOR:' . $this->_ip,
            'CLIENT-IP:' . $this->_ip,
            'X-Requested-With:XMLHttpRequest'
        ];


        $postData = [];

        $this->http_query(
            'http://my.chsi.com.cn/archive/index.action',
            $postData,
            $header_one,
            $cookiePath,
            $taskId
        );


        $res1 = $this->http_query(
            'https://my.chsi.com.cn/archive/gdjy/xj/show.action',
            $postData,
            $header_three,
            $cookiePath,
            $taskId
        );

        \phpQuery::newDocument($res1['data']);

        $luqupic       =   pq("img[alt='录取照片']")->attr('src');

        $xuelipic      =   pq("img[alt='学历照片']")->attr('src');

        $contentpic    =   pq(".xjxx-img")->attr('src');


        $lpic = $this->httpPicToBase64(
            'https://my.chsi.com.cn' . $luqupic,
            $postData,
            $header_three,
            $cookiePath
        );


        $xpic = $this->httpPicToBase64(
            'https://my.chsi.com.cn' . $xuelipic,
            $postData,
            $header_three,
            $cookiePath
        );

        $cpic = $this->httpPicToBase64(
            $contentpic,
            $postData,
            $header_three,
            $cookiePath
        );


        if($lpic['data'] || $cpic['data']){
            //扣费
            Db::table('snake_chsi_user')->where('apikey',$taskIdExits['queryUser'])->setInc('use_num');
            //更新任务时间，状态
            $this->updateTask(
                [
                    'taskId'        =>  $this->_taskId
                ]
                ,
                [
                    'finishTime'    =>  time(),
                    'isOk'          =>  1
                ]
            );
        }



        return [
            'code'  =>  200,
            'msg'   =>  '数据获取成功',
            'data'  =>  [
                'lpic'  =>  $lpic['data'],
                'xpic'  =>  $xpic['data'],
                'cpic'  =>  $cpic['data']
            ]
        ];


    }


    //学信网查询
    public function http_query($url,$postData = [],$headerData = [],$cookiesPath = '',$taskId = '')
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //location 相关操作
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);


        //有cookie则获取并发送
        if($cookiesPath){
            if(file_exists($cookiesPath)){
                curl_setopt ($curl, CURLOPT_COOKIEFILE, $cookiesPath);//用于获取cookie
            }
        }

        //没有cookie则获取并存储
        // if(empty($cookiesPath)){
        $filePath = ROOT_PATH . 'cookie';

        if(!file_exists($filePath)){
            mkdir($filePath);
        }

        $fileName = uniqid() . '.txt';

        $cookiesPath = $filePath . DS  .$fileName ;

        curl_setopt ($curl, CURLOPT_COOKIEJAR, $cookiesPath); //用于接受保存cookie


        //  }

        if($headerData){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData);
        }

        if($postData){
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $res = curl_exec($curl); //var_dump($res);

        curl_close ($curl);

        $this->queryRegix($res,$cookiesPath,$taskId);

        return $status == 0 ? ['data' => $res, 'cookiePath' => $cookiesPath] : false;

    }


    //学信网查询
    public function http_query_captcha($url,$headerData = [],$cookiesPath = '',$paramVal)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        //有cookie则获取并发送
        if($cookiesPath){
            if(file_exists($cookiesPath)){
                curl_setopt ($curl, CURLOPT_COOKIEFILE, $cookiesPath);//用于获取cookie
            }
        }

        //没有cookie则获取并存储
        if(empty($cookiesPath)){
            $filePath = ROOT_PATH . 'cookie';

            if(!file_exists($filePath)){
                mkdir($filePath);
            }

            $fileName = uniqid() . '.txt';

            $cookiesPath = $filePath . DS  .$fileName ;

            curl_setopt ($curl, CURLOPT_COOKIEJAR, $cookiesPath); //用于接受保存cookie


        }

        if($headerData){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $res = curl_exec($curl);

        curl_close ($curl);

        //更新cookie
        $paramVal['update']['cookiePath'] = $cookiesPath;

        $this->updateTask($paramVal['where'],['paramVal' => serialize($paramVal['update'])]);

        return $status == 0 ? ['data' => base64_encode($res), 'cookiePath' => $cookiesPath] : false;

    }


    /*
     * 获取图片 转化为base64
     * */

    public function httpPicToBase64($url,$postData = [],$headerData = [],$cookiesPath = '')
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //location 相关操作
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);


        //有cookie则获取并发送
        if($cookiesPath){
            if(file_exists($cookiesPath)){
                curl_setopt ($curl, CURLOPT_COOKIEFILE, $cookiesPath);//用于获取cookie
            }
        }

        //没有cookie则获取并存储
        // if(empty($cookiesPath)){
        $filePath = ROOT_PATH . 'cookie';

        if(!file_exists($filePath)){
            mkdir($filePath);
        }

        $fileName = uniqid() . '.txt';

        $cookiesPath = $filePath . DS  .$fileName ;

        curl_setopt ($curl, CURLOPT_COOKIEJAR, $cookiesPath); //用于接受保存cookie


        //  }

        if($headerData){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerData);
        }

        if($postData){
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $res = curl_exec($curl); //var_dump($res);

        curl_close ($curl);


        return $status == 0 ? ['data' => base64_encode($res), 'cookiePath' => $cookiesPath] : false;
    }


    /*
     * 正则提取
     * */

    public function queryRegix($data,$cookiePath = '',$taskID = '')
    {
        require_once VENDOR_PATH . 'phpQuery' . DS . 'phpQuery.php';

        $res= \phpQuery::newDocument($data);

        $action       =   pq("#fm1")->attr('action');
        $lt           =   pq("input[name='lt']")->attr('value');
        $captcha      =   pq("#captcha")->attr('name');

        $paramVal     = serialize([
            'action'        =>  $action,
            'lt'            =>  $lt,
            'cookiePath'    =>  $cookiePath
        ]);

        //存储当前任务爬取的相关参数
        Db::table('snake_chsi_api_log')
            ->where(['taskId'   =>  $taskID])
            ->setField('paramVal',$paramVal);

        return [
            'action'        =>  $action,
            'lt'            =>  $lt,
            'captcha'       =>  $captcha,
            'cookiePath'    =>  $cookiePath
        ];
    }


    /*
     * ip生成器
     * */
    public function yeildIp()
    {
        $ip_long = array(
            array('607649792', '608174079'), //36.56.0.0-36.63.255.255
            array('1038614528', '1039007743'), //61.232.0.0-61.237.255.255
            array('1783627776', '1784676351'), //106.80.0.0-106.95.255.255
            array('2035023872', '2035154943'), //121.76.0.0-121.77.255.255
            array('2078801920', '2079064063'), //123.232.0.0-123.235.255.255
            array('-1950089216', '-1948778497'), //139.196.0.0-139.215.255.255
            array('-1425539072', '-1425014785'), //171.8.0.0-171.15.255.255
            array('-1236271104', '-1235419137'), //182.80.0.0-182.92.255.255
            array('-770113536', '-768606209'), //210.25.0.0-210.47.255.255
            array('-569376768', '-564133889'), //222.16.0.0-222.95.255.255
        );

        $rand_key                   = mt_rand(0, 9);
        $ip                         = long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));

        return $ip;
    }


    /*
     * 验证taskid
     * */
    public function checkTaskIdExits($taskId = '')
    {

        $taskCont = Db::table('snake_chsi_api_log')
            ->where(['taskId' => $taskId])
            ->find();

        if(empty($taskCont))
            return [
                'code'  =>  500,
                'msg'   =>  '任务ID不存在'
            ];
        else
            return [
                'code'      =>  200,
                'msg'       =>  '任务ID存在',
                'paramVal'  =>  unserialize($taskCont['paramVal']),
                'userName'  =>  $taskCont['userName'],
                'passWord'  =>  $taskCont['passWord'],
                'queryUser' =>  $taskCont['queryUser'],
            ];

    }

    /*
     * 更新任务内容
     * */
    public function updateTask($where = [],$data = [])
    {
        Db::table('snake_chsi_api_log')
            ->where($where)
            ->update($data);

    }

    /*
     *
     * 上传七牛
     * */

    public static function uploadQiniu($fileName,$filePath)
    {
        require(VENDOR_PATH.'qiniu'. DS .'php-sdk'. DS .'autoload.php');

        $accessKey = '3-2LB-HokLZfe_3e8CpmTivQ80W6nQqtCvSrfkmM';
        $secretKey = 'TcbCsSkjf9dILum8d4fGyWIBsUAHSetCEOHKFXeD';
        $bucket = 'blackpac';

        $auth = new \Qiniu\Auth($accessKey, $secretKey);
        $uptoken = $auth->uploadToken($bucket);     //根据空间取token

        $uploadMgr = new \Qiniu\Storage\UploadManager();
        list($ret, $err) = $uploadMgr->putFile($uptoken,$fileName,$filePath,null);

        if($ret)
            return true;
        else
            return false;
    }
}