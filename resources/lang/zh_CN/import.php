<?php

return [
    'title' => '从文件导入:label',
    'steps' => [
        'upload' => '上传',
        'map' => '映射',
        'review' => '检查',
        'import' => '导入',
    ],
    'upload' => [
        'label' => 'CSV 或 Excel 文件',
        'helper' => '支持 CSV、XLSX、XLS 或 ODS。第一行必须是列标题。大文件会分段处理。',
        'sheet' => '工作表',
        'drop' => '将文件拖放到此处,或点击选择',
        'empty' => '文件没有标题行。',
    ],
    'map' => [
        'heading' => '映射列',
        'description' => '将文件中的列对应到下面的字段。我们已自动填入能识别的列。',
        'required' => '请为「:column」选择一个文件列。',
        'sample' => '例如::values',
        'continue' => '继续',
    ],
    'review' => [
        'checking' => '检查中… 已检查 :checked / :total 行',
        'ready' => '已检查全部 :total 行',
        'valid' => ':count 行可以导入',
        'invalid' => ':count 行有问题(验证失败的行将在导入时被跳过)',
        'row' => '第 :row 行',
        'import' => '导入 :count 行',
        'failed' => '检查未能完成(:count 个部分失败)。请确认队列 worker 正在运行,然后重新开始。',
        'submit_for_approval' => '提交审批',
        'back' => '返回映射',
    ],
    'import' => [
        'importing' => '导入中… 已处理 :processed / :total 行',
        'done' => '导入完成:已导入 :successful / :total 行',
        'failed' => ':count 行失败。请从通知中下载这些行。',
    ],
    'approval' => [
        'submitted' => '已提交审批',
        'hint' => '文件已检查完毕,正在等待审批人处理。导入完成后会通知你。进度和记录可在「导入审计」页面查看。',
    ],
    'preview' => [
        'map' => '预览 — 前几行将如何导入',
        'review' => '前 :count 行(已检查)',
        'empty' => '没有可预览的行。',
    ],
    'notifications' => [
        'started' => '导入已开始',
        'started_body' => '正在导入「:file」中的 :count 行。',
        'finished' => '导入完成',
        'finished_body' => '已导入 :successful / :total 行。',
        'download_failed' => '下载 :count 个失败的行',
    ],
    'action' => [
        'label' => '导入',
        'close' => '关闭',
    ],
    'start_over' => '重新开始',
    'started_over' => '导入已重置',
    'model_importer' => [
        'completed' => '已导入 :count 行。',
        'completed_with_failures' => '已导入 :count 行,:failed 行失败。',
    ],
];
