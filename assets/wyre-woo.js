var processed=0;

var return_url;
var func_return_obj=function () {
     processed=1;
      setTimeout(function(){
         window.location=return_url;
       },5000);
}

var cancel_url;
  var func_cancel_obj=function () {
    if(!processed){
       window.location=cancel_url;
   }
}

function wp_inline(url,cancel,txurl) {
      var obj={};
      obj.url=url;
      return_url=txurl;
      cancel_url=cancel;
      obj.closed=func_cancel_obj;
      obj.success=obj.failed=func_return_obj;
      Wyre.init(obj);
}