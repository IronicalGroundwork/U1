feather.replace();
function buildUrl(overrides) {
    var params = $.extend({}, window.movParams, overrides, {action:'movements'});
    return '?' + $.param(params);
}

$(function(){
    var $form  = $('#mov-form');
    var $input = $('#search-input');
    var $clear = $('#clear-search');

    // Поиск по Enter
    $input.on('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        $form.submit();
      }
    });

    // Показ/скрытие крестика
    $input.on('input', function(){
      $clear.toggle(!!$input.val());
    });

    // Очистка поля + перезагрузка без search
    $clear.on('click', function(){
      $input.val('').trigger('input');
      window.location.href = buildUrl({search:'', page:1});
    });

    // Фильтры по складу/типу/дате (остались без изменений)
    $form.find('select[name="warehouse"], select[name="type"], input[name="date_from"], input[name="date_to"]')
         .on('change', function(){
      $form.find('input[name="page"]').val(1);
      $form.submit();
    });

    // Лимит на странице
    $('#mov-limit').on('change', function(){
      window.location.href = buildUrl({limit:this.value, page:1});
    });

    // Иконки Feather
    if (window.feather) feather.replace();
});