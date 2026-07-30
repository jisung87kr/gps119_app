@extends('errors::minimal')

@section('title', '접근 권한 없음')
@section('code', '403')
{{-- 권한 안내는 서버가 던진 메시지를 우선 노출한다(예: "관제 권한이 없습니다.") --}}
@section('message', $exception->getMessage() ?: '접근 권한이 없습니다')
@section('description', '권한이 필요한 화면입니다. 행사 주최측 또는 상황실에 문의해 주세요.')
