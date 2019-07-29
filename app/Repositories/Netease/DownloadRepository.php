<?php
namespace App\Repositories\Netease;

/**
 *网易相关视频下载
 */
class DownloadRepository {

	public function getData() {
		$uri = config('imconfig.download_url');
		$host = $_SERVER['HTTP_HOST'];
		dd($uri, $host);
		$res = $this->DownloadpostCurl();
	}

	//网易音视频下载
	public function DownloadpostCurl($url = '', $md5 = '', $checkSum = '', $data = '') {
		if (empty($url)) {
			return 'url not null';
		}

		$header = [];

		$header[] = 'Content-Type:application/json';
		$header[] = 'CurTime:' . time();
		if (!empty($md5)) {
			$header[] = 'MD5:' . $md5;
		}
		if (!empty($checkSum)) {
			$header[] = 'CheckSum:' . $checkSum;
		}

		try {
			$curl = curl_init();
			//设置抓取的url
			curl_setopt($curl, CURLOPT_URL, $url);
			//设置头文件的信息作为数据流输出
			curl_setopt($curl, CURLOPT_HEADER, 1);
			//设置获取的信息以文件流的形式返回，而不是直接输出。
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			//输入数据
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			//设置http头信息
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			//执行命令
			$data = curl_exec($curl);
			//关闭URL请求
			curl_close($curl);
			return $data;
		} catch (\Exception $e) {
			return $e->getMessage();
		}

	}
}

?>