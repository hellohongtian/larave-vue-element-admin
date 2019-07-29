<?php

return [
    // 信审处理状态
    'applyStatus' => [
        'notApprove' => [
            'code' => 0,
            'msg'  => '未处理'
        ],
        'approvePass' => [
            'code' => 1,
            'msg'  => '初审通过'
        ],
        'approveFail' => [
            'code' => -1,
            'msg'  => '初审不通过'
        ],
        'approveInvalid' => [
            'code' => -2,
            'msg'  => '初审失效'
        ],
        'approveDiscount' => [
            'code' => -70,
            'msg'  => '初审进件资料有误'
        ],
        'reviewPass' => [
            'code' => 10,
            'msg'  => '复审通过'
        ],
        'reviewFail' => [
            'code' => -10,
            'msg'  => '复审不通过'
        ],
        'reviewInvalid' => [
            'code' => -20,
            'msg'  => '复审失效'
        ],
        'reviewDiscount' => [
            'code' => -80,
            'msg'  => '复审进件资料有误'
        ],
        'reviewReject' => [
            'code' => -30,
            'msg'  => '复审驳回'
        ],
        'autoReject' => [
            'code' => -40,
            'msg'  => '系统自动拒绝'
        ],
        'decisionReject' => [
            'code' => -60,
            'msg'  => '决策引擎拒绝'
        ],
        'adjustAutoReject' => [
            'code' => -50,
            'msg'  => '拒绝可补材料-未补材料不通过'
        ],
        'adjustNotApprove' => [
            'code' => -3,
            'msg'  => '拒绝可补材料-未处理'
        ],
        'adjustApprove' => [
            'code' => 3,
            'msg'  => '拒绝可补材料-已处理'
        ],
        /*
        'adjustApprovePass' => [
            'code' => 4,
            'msg'  => '拒绝可补材料-初审通过'
        ],
        'adjustApproveFail' => [
            'code' => -4,
            'msg'  => '拒绝可补材料-初审不通过'
        ],
        */
        'adjustReviewPass' => [
            'code' => 5,
            'msg'  => '拒绝可补材料-复审通过'
        ],
        'adjustReviewFail' => [
            'code' => -5,
            'msg'  => '拒绝可补材料-复审不通过'
        ],
        'adjust' => [
            'code' => -11,
            'msg'  => '待补录资料'
        ]
    ],

     //优信审批额度
    'bankXinCreditLimit' => [
        '50000' => '5万',
        '80000' => '8万',
        '100000' => '10万',
        '105000' => '10.5万',
        '150000' => '15万',
        '200000' => '20万',
        '250000' => '25万',
        '300000' => '30万',
        '350000' => '35万',
        '400000' => '40万',
        '450000' => '45万',
        '500000' => '50万',
    ],

    //首付比例
    'bankMinPayRatio' => [
        '0.10' => '10%',
        '0.20' => '20%',
        '0.30' => '30%',
        '0.40' => '40%',
        '0.50' => '50%',
    ],

    //优信审批贷款年限
    'loanYear' => [
        '-1' => '不限制',
        '1' => '1',
        '2' => '2',
        '3' => '3',
    ],
];