<?php
//核心操作文件
function du_init($quickinit = false){
  define('BPCSU_KEY','uFBSHEwWE6DD94SQx9z77vgG');
  define('BPCSU_SEC','7w6wdSFsTk6Vv586r1W1ozHLoDGhXogD');
  define('BPCSU_FNAME','bpcs_uploader');
  if($quickinit){
    //快速初始化
    $appkey = BPCSU_KEY;
    file_put_contents(CONFIG_DIR.'/appkey',$appkey);
    $appsec = BPCSU_SEC;
    file_put_contents(CONFIG_DIR.'/appsec',$appsec);
    $appname = BPCSU_FNAME;
    file_put_contents(CONFIG_DIR.'/appname',$appname);
  }else{
    //正常初始化
    echo <<<EOF
Please enter your PSC App API Key. You can get this key by visiting http://developer.baidu.com/dev#/create
If you already created an app, you can visit http://developer.baidu.com/console#/app and get it in your app\'s info.
If you don\'t want to bother creating an app, you can press Enter to use the demo API Key.
Doing so (without your own API Key/Secret) will cause the access-token expire every 30 days, and you\'ll have to
re-initialize when it expires.

EOF;
    echo 'App API KEY ['.BPCSU_KEY.'] :';
    $appkey = getline();
    $appkey = ($appkey) ? $appkey : BPCSU_KEY;
    file_put_contents(CONFIG_DIR.'/appkey',$appkey);
    echon('App API Key has been set to '.$appkey.' . ');
    if($appkey == BPCSU_KEY){
      echon('Demo key detected. Using default API Secret.');
      $appsec=BPCSU_SEC;
    }else{
      echo <<<EOF
  Please enter your Baidu PSC App API Secret. If you have no idea what it is, keep it blank.

EOF;
      echo 'App API SECRET [] :';
      $appsec = getline();
    }
    file_put_contents(CONFIG_DIR.'/appsec',$appsec);
    $prepathfile = CONFIG_DIR.'/appname';
    if($appkey == BPCSU_KEY){
      echon('Demo key detected. Using default app name.');
      $appname = 'bpcs_uploader';
    }else{
      echo <<<EOF
Please enter your app\'s folder name. You can choose to input this later in the file [ $prepathfile ].
** Why do I have to enter the app\'s folder name? Please check the FAQs. **
If your app\'s name has Chinese characters, please ensure that your client supports UTF-8 encoding.
Below are some Chinese characters for testing. Please make sure that you can read them before you enter Chinese here.
这里是一些中文字符。
If you can\'t read the characters above, please press Enter and change it manually within the file [ $prepathfile ].

EOF;
      echo 'App\'s Folder Name [] : ';
      $appname = getline();
    }
    file_put_contents(CONFIG_DIR.'/appname',$appname);
  }//end of 初始化配置

  if($appsec){
    $tokens=du_oauth_device($appkey,$appsec);
    $access_token = $tokens['access_token'];
    $refresh_token = $tokens['refresh_token'];
  }else{
    $access_token = do_oauth_token($appkey);
    $refresh_token = '';
  }
  file_put_contents(CONFIG_DIR.'/access_token',$access_token);
  file_put_contents(CONFIG_DIR.'/refresh_token',$refresh_token);

  $quota = get_quota($access_token);
  $u=$quota['used']/1024/1024/1024;$a=$quota['quota']/1024/1024/1024;
  echon(sprintf("Access Granted. Your Storage Status: %.2fG/%.2fG (%.2f%%)",$u,$a,$u/$a*100));
  echon('Enjoy!');
}
function du_oauth_device($appkey,$appsec){
  $device_para = 'client_id='.$appkey.'&response_type=device_code&scope=basic,netdisk';
  $device_json = do_api('https://openapi.baidu.com/oauth/2.0/device/code',$device_para);
  $device_array = json_decode($device_json,1);
  oaerr($device_array);

  echo <<<EOF
Launch your favorite web browser and visit $device_array[verification_url]
Input $device_array[user_code] as the user code if asked.
After granting access to the application, come back here and press Enter to continue.

EOF;
  getline();
  for(;;){
    //一个死循环
    $token_para='grant_type=device_token&code=' . $device_array['device_code'] . '&client_id=' . $appkey . '&client_secret=' . $appsec;
    $token_json = do_api('https://openapi.baidu.com/oauth/2.0/token',$token_para);
    $token_array = json_decode($token_json,1);
    if(oaerr($token_array,0)){
      break;
    }else{
      echon('Authentication failed. Please check the error message and try again.');
      echo <<<EOF
Launch your favorite web browser and visit $device_array[verification_url]
Input $device_array[user_code] as the user code if asked.
After granting access to the application, come back here and press Enter to continue.

EOF;
      continueornot();
      continue;
    }
    break;
  }
  $access_token = $token_array['access_token'];
  $refresh_token = $token_array['refresh_token'];
  return array(
    'access_token' => $access_token,
    'refresh_token' => $refresh_token,
  );
}
function do_oauth_token($appkey){
  echo <<<EOF
In the next step, you\'ll have to grab the access_token generated by Baidu.
You can check out this link for more information on this procedure.
http://developer.baidu.com/wiki/index.php?title=docs/pcs/guide/usage_example

Easy Guide:
1. Visit https://openapi.baidu.com/oauth/2.0/authorize?response_type=token&client_id=$appkey&redirect_uri=oob&scope=netdisk
2. After the page is being redirected (it should show something like OAuth 2.0), copy the current URL to your favorite text editor.
3. Grab the access_token part, take only the part between \"access_token=\" and the next \"&\" symbol (without quotes).
4. Copy it and paste here, then press Enter.

EOF;
  echo 'access_token[] : ';
  $access_token = getline();
  return $access_token;
}
function do_oauth_refresh($appkey,$appsec,$refresh_token){
  $para = 'grant_type=refresh_token&refresh_token='.$refresh_token.'&client_id='.$appkey.'&client_secret='.$appsec;
  $token_json = do_api('https://openapi.baidu.com/oauth/2.0/token',$para);
  $token_array = json_decode($token_json,1);
  $access_token = $token_array['access_token'];
  $refresh_token = $token_array['refresh_token'];
  return array(
    'access_token' => $access_token,
    'refresh_token' => $refresh_token,
  );
}
function get_quota($access_token){
  $quota=do_api('https://pcs.baidu.com/rest/2.0/pcs/quota',"method=info&access_token=".$access_token,'GET');
  $quota=json_decode($quota,1);
  apierr($quota);
  return $quota;
}
function upload_file($access_token,$path,$localfile,$ondup='newcopy'){
  $path = getpath($path);
  $url = "https://c.pcs.baidu.com/rest/2.0/pcs/file?method=upload&access_token=$access_token&path=$path&ondup=$ondup";
  $add = "--form file=@$localfile";
  $cmd = "curl -X POST -k -L $add \"$url\"";
  $cmd = cmd($cmd);
  $cmd = json_decode($cmd,1);
  apierr($cmd);
  return $cmd;
}
function delete_file($access_token,$path){
  $path = getpath($path);
  $dele=do_api('https://pcs.baidu.com/rest/2.0/pcs/file',"method=delete&access_token=".$access_token.'&path='.$path,'GET');
  $dele=json_decode($dele,1);
  apierr($dele);
  return $dele;
}
function fetch_file($access_token,$path,$url){
  $path = getpath($path);
  $fetch=do_api('https://pcs.baidu.com/rest/2.0/pcs/services/cloud_dl',"method=add_task&access_token=".$access_token.'&save_path='.$path.'&source_url='.$url,'GET');
  $fetch=json_decode($fetch,1);
  apierr($fetch);
  return $fetch;
}
//分片上传
function super_file($access_token,$path,$localfile,$ondup='newcopy',$sbyte=1073741824,$temp_dir='/tmp/'){
  //调用split命令进行切割
  //split -b200 --verbose rubygems-1.8.25.zip rg/rg1
  if(filesize($localfile)<=$sbyte){
    echon('The file isn\'t big enough to split up. Proceed to upload normally.');
    upload_file($access_token,$path,$localfile,$ondup);	//直接上传
  }
  $tempfdir = rtrim($temp_dir,'/').'/'.uniqid('bpcs_to_upload_');
  if(!mkdir($tempfdir,0700,true)){
    echon('Cannot create temp dir:'.$tempfdir);
    die(9009);
  }
  $splitcmd = "split -b{$sbyte} $localfile $tempfdir/bpcs_toupload_";
  $splitresult = cmd($splitcmd);
  if(trim($splitresult)){
    echon('Split exited with message:'.$splitresult);
  }
  //遍历临时文件目录
  $tempfiles = glob($tempfdir.'/bpcs_toupload_*');
  if(count($tempfiles)<1){
    //没有生成文件
    echon('There are no files to be upload.');
    die(9010);
  }elseif(count($tempfiles)==1){
    //只有一个文件
    unlink($tempfiles[0]);	//删除它
    echon('The file isn\'t big enough to split up. Proceed to upload normally.');
    upload_file($access_token,$path,$localfile,$ondup);	//直接上传
    return;
  }
  //开始上传进程
  $block_list = array();
  $count = 0;
  foreach($tempfiles as $tempfile){
    //上传临时文件，上传API与上传普通文件无异，只是多一个参数type=tmpfile，取消了其它几个参数。此处将“&type=tmpfile”作为ondup传递，将参数带在请求尾部。
    echon('Uploading file in pieces, '.($count+1).' out of '.count($tempfiles).' parts... ');
    $count++;
    $upload_res = upload_file($access_token,'',$tempfile,$ondup.'&type=tmpfile');
    $block_list[] = $upload_res['md5'];
    //删除临时文件
    unlink($tempfile);
  }
  //删除临时文件夹
  rmdir($tempfdir);
  //准备提交API
  $block_list = json_encode($block_list);
  $param = '{"block_list":'.$block_list.'}';
  $param = 'param='.urlencode($param);
  $path = getpath($path);
  $url = "https://pcs.baidu.com/rest/2.0/file?method=createsuperfile&path={$path}&access_token={$access_token}";
  $res = do_api($url,$param);
}
