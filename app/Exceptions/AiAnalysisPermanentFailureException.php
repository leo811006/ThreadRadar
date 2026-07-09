<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 標記「重試也不會成功」的 AI 分析失敗（安全過濾器封鎖、回應格式永久不符預期），
 * 與網路逾時/限流等暫時性錯誤區分，讓 AnalyzePostJob 據此決定是否放棄重試。
 */
class AiAnalysisPermanentFailureException extends RuntimeException {}
