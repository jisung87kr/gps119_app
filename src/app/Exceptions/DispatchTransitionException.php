<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 지령 전이 도메인 예외 (SPEC-04 규칙6). 컨트롤러에서 422 로 변환.
 */
class DispatchTransitionException extends RuntimeException {}
