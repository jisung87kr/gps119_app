<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 지령 권한 도메인 예외 (SPEC-04 규칙1·5). 컨트롤러에서 403 으로 변환.
 */
class DispatchAuthorizationException extends RuntimeException {}
