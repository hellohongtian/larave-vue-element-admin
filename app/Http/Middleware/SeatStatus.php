<?php
namespace App\Http\Middleware;

use App\Models\VideoVisa\SeatManage;
use Closure;
// use Illuminate\Http\RedirectResponse as Redirect;
use Illuminate\Http\Response;
use App\Http\Controllers\FaceAuth\FaceAuthController;
/**
 *
 */
class seatStatus {

	/**
	 * 运行请求过滤器。
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next) {
		$response = $next($request);

		$seat_id = session('uinfo.seat_id');
		$seatModel = new SeatManage();
		$status = $seatModel->select('work_status')->where('id',$seat_id)->first();
		if ($status) {
			$status = $status->toArray();
			if ($status['work_status'] == SeatManage::SEAT_WORK_STATUS_BUSY) {
				// $c = new FaceAuthController();
				// return $c->index($request);
				$current_url = $request->getRequestUri();
				if ($current_url == '/fast-visa/wait_list') {
					
				}else{
					//return back(); todo tanrenzong 暂时关闭页面默认跳转
				}
				
			}else{
				return $response;
			}
		} else {
			return $response;
		}
		return $response;

	}
}

?>