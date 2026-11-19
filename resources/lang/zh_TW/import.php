<?php

return [
    'title' => '從檔案匯入:label',
    'steps' => [
        'upload' => '上傳',
        'map' => '對應',
        'review' => '檢查',
        'import' => '匯入',
    ],
    'upload' => [
        'label' => 'CSV 或 Excel 檔案',
        'helper' => '支援 CSV、XLSX、XLS 或 ODS。第一列必須是欄位標題。大型檔案會分段處理。',
        'sheet' => '工作表',
        'drop' => '將檔案拖放到此處,或點擊選擇',
        'empty' => '檔案沒有標題列。',
    ],
    'map' => [
        'heading' => '對應欄位',
        'description' => '將檔案中的欄位對應到下方的欄位。我們已自動填入能辨識的欄位。',
        'required' => '請為「:column」選擇一個檔案欄位。',
        'sample' => '例如::values',
        'continue' => '繼續',
    ],
    'review' => [
        'checking' => '檢查中… 已檢查 :checked / :total 列',
        'ready' => '已檢查全部 :total 列',
        'valid' => ':count 列可以匯入',
        'invalid' => ':count 列有問題(驗證失敗的列將在匯入時略過)',
        'row' => '第 :row 列',
        'import' => '匯入 :count 列',
        'failed' => '檢查未能完成(:count 個部分失敗)。請確認佇列 worker 正在執行,然後重新開始。',
        'submit_for_approval' => '提交審核',
        'back' => '返回對應',
    ],
    'import' => [
        'importing' => '匯入中… 已處理 :processed / :total 列',
        'done' => '匯入完成:已匯入 :successful / :total 列',
        'failed' => ':count 列失敗。請從通知中下載這些列。',
    ],
    'approval' => [
        'submitted' => '已提交審核',
        'hint' => '檔案已檢查完畢,正在等待審核者處理。匯入完成後會通知你。進度與紀錄可在「匯入稽核」頁面查看。',
    ],
    'preview' => [
        'map' => '預覽 — 前幾列將如何匯入',
        'review' => '前 :count 列(已檢查)',
        'empty' => '沒有可預覽的列。',
    ],
    'notifications' => [
        'started' => '匯入已開始',
        'started_body' => '正在匯入「:file」中的 :count 列。',
        'finished' => '匯入完成',
        'finished_body' => '已匯入 :successful / :total 列。',
        'download_failed' => '下載 :count 筆失敗的列',
    ],
    'action' => [
        'label' => '匯入',
        'close' => '關閉',
    ],
    'start_over' => '重新開始',
    'started_over' => '匯入已重設',
    'model_importer' => [
        'completed' => '已匯入 :count 列。',
        'completed_with_failures' => '已匯入 :count 列,:failed 列失敗。',
    ],
];
