(function(){

    var _cacheData = {
        ranking:null,
        agenda:null
    };

    var _r;

    function retrieveData(pWhat, pCallBack)
    {
        if(_cacheData[pWhat] !== null)
        {
            pCallBack(_cacheData[pWhat]);
            return;
        }
        if(_r)
        {
            _r.cancel();
        }
        _r = new Request('php/backend.php?what='+pWhat);
        _r.onComplete(function(pResponse)
        {
            _cacheData[pWhat] = pResponse.responseJSON;
            console.log(pWhat);
            console.log(_cacheData[pWhat]);
            pCallBack(_cacheData[pWhat]);
        });
    }

    FwJs.newController('Index', null, {
        agenda:function()
        {
            var ref = this;
            retrieveData("agenda", function(pData){
                ref.addContent('data', pData);
                ref.dispatchEvent(new Event(FwJs.lib.events.RENDER));
            });
        },
        ranking:function()
        {
            var ref = this;
            retrieveData("ranking", function(pData){
                ref.addContent('data', pData);
                ref.dispatchEvent(new Event(FwJs.lib.events.RENDER));
            });
        }
    });
    window.addEventListener('load', FwJs.start);
})();