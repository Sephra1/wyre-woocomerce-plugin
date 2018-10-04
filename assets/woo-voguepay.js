var rd_url;
var processed=0;

var functionobj=function () {
     processed=1;
      setTimeout(function(){
         window.location=rd_url;
       },5000);
  }

var cn_url;
  var x_functionobj=function () {
    if(!processed){
       window.location=cn_url;
   }
}

  function vp_inline(url,text,cancel,txurl) {
        var obj={};
        obj.url=url;
        rd_url=txurl;
        cn_url=cancel;
         obj.closed=x_functionobj;
        obj.success=obj.failed=functionobj;
        if(text.trim().length>0) obj.loadText=text;
        Wyrepay.link(obj);
   }

