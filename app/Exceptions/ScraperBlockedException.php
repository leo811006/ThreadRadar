<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 非官方爬蟲疑似被目標網站封鎖/限流（如回傳零結果但選擇器等待逾時、驗證碼頁面等）。
 * 與暫時性錯誤（網路逾時等）區分：這類狀態短時間內重試大機率仍會失敗，甚至可能
 * 加劇封鎖，故呼叫端應比照 QuotaExceededException 不重試、交由下一輪排程再嘗試。
 */
class ScraperBlockedException extends RuntimeException {}
