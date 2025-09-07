(function($){
  $(function(){

    // Remove chip (one value) -> rebuild querystring
    $(document).on('click','.sello-chip',function(e){
      e.preventDefault();
      var $chip = $(this);
      var p = $chip.data('param');
      var v = String($chip.data('value'));
      var params = new URLSearchParams(window.location.search);
      if (params.has(p)) {
        // p can be multiple values -> array style ?p[]=.. handled by URL manually:
        // We used name="p[]" which serializes as multiple p= values, not p[]= by GET submit.
        var values = params.getAll(p);
        var idx = values.indexOf(v);
        if (idx>-1) values.splice(idx,1);
        params.delete(p);
        values.forEach(function(val){ params.append(p,val); });
      }
      var qs = params.toString();
      window.location.search = qs ? ('?'+qs) : window.location.pathname;
    });

    // Optional: auto-submit on change
    $(document).on('change','.sello-filters-form input, .sello-filters-form select',function(){
      // Uncomment to auto-apply
      // $(this).closest('form').trigger('submit');
    });

  });
})(jQuery);
