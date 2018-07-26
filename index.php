<?php

function curlSet($ch) {
	$cookieFile = dirname(__FILE__) . '/cookie.txt';
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_REFERER, 'https://mp.weixin.qq.com');
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
}

function myLog($str, $needDie = false) {
	echo $str . '<br>';
	ob_flush();
	flush();
	if ($needDie) {
		die();
	}
}

$apis = [
    "host"         => "https://mp.weixin.qq.com",
    "startlogin"   => "https://mp.weixin.qq.com/cgi-bin/bizlogin?action=startlogin",
    "getqrcode"    => "https://mp.weixin.qq.com/cgi-bin/loginqrcode?action=getqrcode&param=4300&rd=192",
    "ask"          => "https://mp.weixin.qq.com/cgi-bin/loginqrcode?action=ask&token=&lang=zh_CN&f=json&ajax=1",
    "loginauth"    => "https://mp.weixin.qq.com/cgi-bin/loginauth?action=ask&token=&lang=zh_CN&f=json&ajax=1",
    "login"        => "https://mp.weixin.qq.com/cgi-bin/bizlogin?action=login&lang=zh_CN",
];
$options = [
	'user' => '请填入用户名',
	'pwd' => '请填入密码',
];
$tokenFile = 'token.txt';

if (file_exists($tokenFile)) {
	$token = file_get_contents($tokenFile);
	if ($token) {
		myLog("已经获取token：{$token}！", true);
	}
}

// 访问主页，获取初始cookie
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apis['host']);
curlSet($ch);
curl_exec($ch);
curl_close($ch);

// 模拟登录
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apis['startlogin']);
curlSet($ch);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
	'username' => $options['user'],
	'pwd' => md5($options['pwd']),
	'imgcode' => '',
	'f' => 'json',
	'userlang' => 'zh_CN',
	'token' => '',
	'lang' => 'zh_CN',
	'ajax' => '1',
]);
$res = curl_exec($ch);
if (empty($res)) {
	die(trigger_error(curl_errno($ch)));
}
curl_close($ch);
// {"base_resp":{"err_msg":"ok","ret":0},"redirect_url":"/cgi-bin/bizlogin?action=validate&lang=zh_CN&account=liangxiaowen1989%40dingtalk.com"}
$res = json_decode($res, true);
if ($res['base_resp']['ret']) {
	die(trigger_error("{$res['base_resp']['ret']}: {$res['base_resp']['err_msg']}"));
}

// 获取二维码
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apis['getqrcode']);
curlSet($ch);
$res = curl_exec($ch);
if (empty($res)) {
	die(trigger_error(curl_errno($ch)));
}
curl_close($ch);
file_put_contents('qrcode.jpg', $res);
myLog('<img src="qrcode.jpg">');

// 查询扫码状态
$ch = curl_init();
curlSet($ch);
$url = $apis['ask'];
while(1) {
	curl_setopt($ch, CURLOPT_URL, $url);
	$res = curl_exec($ch);
	if (empty($res)) {
		die(trigger_error(curl_errno($ch)));
	}
	// {"base_resp":{"err_msg":"ok","ret":0},"status":0,"user_category":0}
	$res = json_decode($res, true);
	$needBreak = 0;
	switch ($res['status']) {
		case 1:
			if (1 == $res['user_category']) {
				$url = $apis['loginauth'];
			} else {
				myLog('登录成功！');
				$needBreak = 1;
			}
			break;
		case 2: myLog('管理员拒绝！', true); break;
		case 3: myLog('登录超时！', true); break;
		case 4: myLog('已经扫码！'); break;
		default: myLog('等待扫码...'); break;
	}
	if ($needBreak) {
		break;
	}
	sleep(1);
}
curl_close($ch);

// 获取token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apis['login']);
curlSet($ch);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
	'userlang' => 'zh_CN',
	'token' => '',
	'lang' => 'zh_CN',
	'f' => 'json',
	'ajax' => '1',
]);
$res = curl_exec($ch);
if (empty($res)) {
	die(trigger_error(curl_errno($ch)));
}
curl_close($ch);
// {"base_resp":{"err_msg":"ok","ret":0},"redirect_url":"/cgi-bin/home?t=home/index&lang=zh_CN&token=749528398"}
$res = json_decode($res, true);
if ($res['base_resp']['ret']) {
	die(trigger_error("{$res['base_resp']['ret']}: {$res['base_resp']['err_msg']}"));
}
$token = '';
preg_match('/token=([\d]+)/i', $res['redirect_url'], $token);
$token = $token[1];
myLog("成功获取token: 【{$token}】！");
file_put_contents($tokenFile, $token);
