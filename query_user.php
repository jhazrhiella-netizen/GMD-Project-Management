<?php
require 'src/load_env.php';
$id='8bd758a5-9f27-4ad1-b5e6-e0364e05cab7';
$base=rtrim(getenv('SUPABASE_URL'), '/');
$anon=getenv('SUPABASE_ANON_KEY');
$svc=getenv('SUPABASE_KEY');
function req($url,$headers){
  $opts=['http'=>['method'=>'GET','header'=>implode("\r\n",$headers)."\r\n"]];
  $ctx=stream_context_create($opts);
  $r=@file_get_contents($url,false,$ctx);
  $h=$http_response_header??[];
  return ['status'=>is_array($h)?(int)preg_replace('/^HTTP\/\d+\.\d+\s+(\d+).*/','$1',$h[0] ?? '0') : 0,'body'=>$r];
}
$profile=req($base.'/rest/v1/profiles?id=eq.'.$id.'&select=*',['apikey: '.$anon,'Authorization: Bearer '.$anon,'Accept: application/json']);
$admin=req($base.'/auth/v1/admin/users/'.$id,['apikey: '.$svc,'Authorization: Bearer '.$svc,'Accept: application/json']);
echo json_encode(['profile'=>$profile,'admin'=>$admin]);
