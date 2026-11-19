<?php

return [
    'title' => '导入审计',
    'navigation' => '导入审计',
    'columns' => [
        'when' => '时间',
        'uploaded_by' => '上传者',
        'file' => '文件',
        'rows' => '行数',
        'problems' => '问题',
        'status' => '状态',
    ],
    'status' => [
        'uploaded' => '已上传',
        'reviewed' => '已检查',
        'pending_approval' => '待审批',
        'approved' => '已批准',
        'rejected' => '已拒绝',
        'importing' => '导入中',
        'completed' => '已完成',
        'completed_with_failures' => '已完成(部分失败)',
    ],
    'actions' => [
        'history' => '记录',
        'close' => '关闭',
        'approve' => '批准',
        'approve_description' => '文件将立即以上传者的身份导入。',
        'reject' => '拒绝',
        'note' => '备注(选填)',
        'reason' => '原因',
    ],
    'history' => [
        'heading' => '导入 #:id',
        'checksum' => 'SHA-256',
        'summary' => ':rows 行 · :problems 行有问题',
        'result' => '已导入 :imported 行,失败 :failed 行',
        'user' => '用户 #:id',
    ],
    'notifications' => [
        'approved' => '导入已批准并开始',
        'rejected' => '导入已拒绝',
        'cannot_decide' => '你无权批准或拒绝此导入。',
    ],
    'errors' => [
        'already_final' => '审计 #:id 已是「:status」状态。',
        'no_column_map' => '审计 #:id 没有已检查的列映射。',
    ],
];
