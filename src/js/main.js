(function(){

    var _cacheData = {
        ranking:{},
        agenda:{}
    };

    var type = "RME";

    var _r;

    function retrieveData(pWhat, pCallBack)
    {
        if(_cacheData[pWhat][type])
        {
            pCallBack(_cacheData[pWhat][type]);
            return;
        }
        document.querySelector('#loader').style.display='block';
        if(_r)
        {
            _r.cancel();
        }
        _r = new Request('php/backend.php?what='+pWhat+"&who="+type);
        _r.onComplete(function(pResponse)
        {
            _cacheData[pWhat][type] = pResponse.responseJSON;
            document.querySelector('#loader').style.display='none';
            pCallBack(_cacheData[pWhat][type]);
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
            this.addEventListener(FwJs.lib.events.RENDER_COMPLETE, function(){
                document.querySelector('select#rub').addEventListener('change', function(e){
                    document.querySelectorAll('.ranking').forEach(function(pItem){
                        pItem.style.display = 'none';
                    });
                    document.querySelector('.ranking[rel="'+e.currentTarget.value+'"]').style.display = 'block';
                });
            });
            retrieveData("ranking", function(pData){
                ref.addContent('data', pData);
                ref.dispatchEvent(new Event(FwJs.lib.events.RENDER));
            });
        },
        teams:function()
        {
            this.addContent('type', type);
            this.addEventListener(FwJs.lib.events.RENDER_COMPLETE, function(){
                document.querySelectorAll('input[name="type"]').forEach(function(pItem){
                    pItem.addEventListener('change', function(e){
                        document.querySelector('div.selected').classList.remove('selected');
                        e.currentTarget.parentNode.classList.add("selected");
                        type = e.currentTarget.value;
                    });
                });
            });
            this.dispatchEvent(new Event(FwJs.lib.events.RENDER));
        }
    });
    window.addEventListener('load', FwJs.start);
})();