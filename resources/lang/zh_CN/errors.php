<?php

return [
    'invalid_request' => '请求无效。',
    'unauthenticated' => '未登录。',
    'upload_failed' => '上传失败(:status)',
    'unknown_upload' => '未知的上传。',
    'unsupported_type' => '不支持的文件类型。',
    'empty_file' => '文件是空的。',
    'too_large' => '文件超过允许的大小。',
    'chunk_out_of_range' => '分段序号超出范围。',
    'chunk_wrong_size' => '第 :index 段大小不正确。',
    'chunk_missing' => '缺少第 :index 段。',
    'size_mismatch' => '合并后的文件与声明的大小不符。',
    'spreadsheet_unreadable' => '无法读取电子表格::reason',
    'xls_needs_phpspreadsheet' => '导入 .xls 文件需要 phpoffice/phpspreadsheet(composer require phpoffice/phpspreadsheet),或将文件另存为 .xlsx。',
];
