<?php 
/**
 * @description 
 * @date Mar 13, 2019
 * @author shenxin
 */
return array(
    'system_hook_config'=>array(
        'system_after_run_end_monitor'=>array(
            //执行完了后的回调处理
            'SystemErrorHandler'=>'\Hook\Trace\AfterScriptRunEndTrace',
        ),
    ),
);