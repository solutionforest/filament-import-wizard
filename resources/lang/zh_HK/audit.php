<?php

return [
    'title' => '匯入審計',
    'navigation' => '匯入審計',
    'columns' => [
        'when' => '時間',
        'uploaded_by' => '上傳者',
        'file' => '檔案',
        'rows' => '行數',
        'problems' => '問題',
        'status' => '狀態',
    ],
    'status' => [
        'uploaded' => '已上傳',
        'reviewed' => '已檢查',
        'pending_approval' => '待審批',
        'approved' => '已批准',
        'rejected' => '已拒絕',
        'importing' => '匯入中',
        'completed' => '已完成',
        'completed_with_failures' => '已完成(部分失敗)',
    ],
    'actions' => [
        'history' => '紀錄',
        'close' => '關閉',
        'approve' => '批准',
        'approve_description' => '檔案將立即以上傳者的身分匯入。',
        'reject' => '拒絕',
        'note' => '備註(選填)',
        'reason' => '原因',
    ],
    'history' => [
        'heading' => '匯入 #:id',
        'checksum' => 'SHA-256',
        'summary' => ':rows 行 · :problems 行有問題',
        'result' => '已匯入 :imported 行,失敗 :failed 行',
        'user' => '使用者 #:id',
    ],
    'notifications' => [
        'approved' => '匯入已批准並開始',
        'rejected' => '匯入已拒絕',
        'cannot_decide' => '你無權批准或拒絕此匯入。',
    ],
    'errors' => [
        'already_final' => '審計 #:id 已是「:status」狀態。',
        'no_column_map' => '審計 #:id 沒有已檢查的欄位對應。',
    ],
];
