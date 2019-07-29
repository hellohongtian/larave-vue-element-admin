<?php
//网易即时通讯IM配置文件
return [
	//开发者平台appkey
	'AppKey' => '83b5cdb281cb4a9e193bb2e687613370',
	//秘钥
	'AppSecret' => 'f0f6f0c261c2',
	//redis 校验和的key值
	'imCheckSumRedisKey' => 'wangyi_im_checksum_redis_key',

	'actionUrl' => [
		//创建网易云通信ID
		'create_user' => 'https://api.netease.im/nimserver/user/create.action',
		//更新网易云通信ID
		'update_user' => 'https://api.netease.im/nimserver/user/updateUinfo.action',
		//封禁网易云通信ID
		'block_user' => 'https://api.netease.im/nimserver/user/block.action',
		//解禁网易云通信ID
		'unblock_user' => 'https://api.netease.im/nimserver/user/unblock.action',
		//获取名片
		'getUserInfo' => 'https://api.netease.im/nimserver/user/getUinfos.action',
		//更新并获取新token
		'refreshToken' => 'https://api.netease.im/nimserver/user/refreshToken.action',
	],
	//网易音视频，白班内容下载类型
	'download_eventType' => 6,
	'download_url' => 'http://yunxinservice.com.cn/receiveMsg.action',
	'download_Content_Type' => 'application/json',

];