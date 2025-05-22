// public/js/products.js
document.addEventListener('DOMContentLoaded', () => {
    if (window.feather) {
      feather.replace();
    } else {
      console.error('Feather Icons не загружен');
    }
  });

function showOverlay() {
  $('#page-overlay').show();
}
function hideOverlay() {
  $('#page-overlay').hide();
}

$(function(){

  const $input = $('#search-input');
  const $clear = $('#clear-search');

  // Поиск по нажатию Enter
  $input.on('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      $(this).closest('form').submit();
    }
  });

  // Показ/скрытие крестика
  $input.on('input', function(){
    $clear.toggle(!!$(this).val());
  });

  // Очистка поля и перезагрузка страницы для сброса поиска
  $clear.on('click', function(){
    $input.val('').trigger('input');
    // Перезагрузить без параметра search, чтобы сбросить фильтр
    const base = window.location.pathname;
    window.location.href = base + '?action=products';
  });

   // Копирование артикула
  $(document).on('click', '.offer-cell', function(){
    const txt = $(this).text().trim();
    if (!txt) return;
    navigator.clipboard.writeText(txt)
      .then(() => {
        // либо alert, либо собственный toast
        const info = $('<div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="position:fixed;bottom:1rem;right:1rem;">\
          <div class="d-flex">\
            <div class="toast-body">Скопировано: ' + txt + '</div>\
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>\
          </div>\
        </div>');
        $('body').append(info);
        const bs = new bootstrap.Toast(info);
        bs.show();
        info.on('hidden.bs.toast', ()=> info.remove());
      })
      .catch(()=> alert('Не удалось скопировать'));
  });

  // Обновить товары из маркетплейсов
  $('#btn-refresh-products').on('click', function(){
    const btn = $(this).prop('disabled', true).text('Идёт обновление…');
    showOverlay();
    $.post('?action=refresh-products', {}, function(res){
      if (res.error) {
        alert('Ошибка: ' + res.error);
        hideOverlay();
      } else {
        alert(
          `Товары обновлены:\n` +
          `— обработано товаров: ${res.products_processed}\n` +
          `— добавлено подтверждений: ${res.confirmations_added}`
        );
        location.reload();
      }
    }, 'json')
    .fail(function(xhr){
      alert('Серверная ошибка: ' + xhr.statusText);
      hideOverlay();
    })
    .always(function(){
      btn.prop('disabled', false).text('Обновить товары');
    });
  });

  // Обновить остатки
  $('#btn-refresh-stock').on('click', function(){
    const btn = $(this).prop('disabled', true).text('Обновление…');
    showOverlay();
    $.post('?action=refresh-stocks', {}, function(res){
      if (res.error) {
        alert('Ошибка: ' + res.error);
        hideOverlay();
      } else {
        alert('Остатки обновлены: ' + res.updated_at);
        location.reload();
      }
    }, 'json')
    .fail(function(xhr){
      alert('Серверная ошибка: ' + xhr.statusText)
      hideOverlay();
    })
    .always(() => btn.prop('disabled', false).text('Обновить остатки'));
  });

  
  // Сгенерировать наборы
  $('#btn-generate-sets').on('click', function(){
    const btn = $(this).prop('disabled', true).text('Генерация…');
    showOverlay();
    $.post('?action=generate-sets', {}, function(res){
      if (res.error) {
        alert('Ошибка: ' + res.error);
        hideOverlay();
      } else {
        alert('Наборы сгенерированы: ' + res.generated);
        location.reload();
      }
    }, 'json')
    .fail(function(xhr){
      alert('Серверная ошибка: ' + xhr.statusText)
      hideOverlay();
    })
    .always(() => btn.prop('disabled', false).text('Сгенерировать наборы'));
  });

   // При открытии дропдауна — подгружаем список складов и строим форму
  $(document).on('show.bs.dropdown', '.dropdown', function(){
    const btn       = $(this).find('.dropdown-toggle');
    const pid       = btn.data('product-id');
    const container = $(this).find('.stock-content');

    if (btn.data('loaded')) return;
    container.html('<div>Загрузка…</div>');

    $.post('?action=get-product-stock', { product_id: pid }, function(data){
      if (!data.length) {
        container.html('<div>Складов нет</div>');
        return;
      }

      // 1) Собираем <select> вариантов
      let select = $(`<select class="form-select mb-2 warehouse-select"></select>`);
      data.forEach(w => {
        select.append(
          `<option value="${w.warehouse_id}" data-qty="${w.quantity || 0}">
             ${w.warehouse_name} (${w.quantity || 0})
           </option>`
        );
      });

      // 2) Инпут для редактирования
      let input = $(
        `<input type="number" class="form-control mb-2 stock-edit-input"
                value="${data[0].quantity || 0}" />`
      );

      // 3) Кнопки
      let btnSave   = $(`<button class="btn btn-light" disabled>Сохранить</button>`);
      let btnCancel = $(`<button class="btn btn-light">Отмена</button>`);

      // Обёртка в flex, чтобы быть в одной строке и равного размера
      let actionWrapper = $('<div class="d-flex"></div>');
      btnSave.addClass('flex-fill me-1');
      btnCancel.addClass('flex-fill ms-1');
      actionWrapper.append(btnSave, btnCancel);

      // Рендерим всё в контейнере
      container.empty().append(select, input, actionWrapper);
      btn.data('loaded', true);

      // 4) Логика включения Save при изменении
      let originalQty = parseInt(select.find('option:selected').data('qty'), 10);
      function checkChanged(){
        const cur = parseInt(input.val(),10);
        btnSave.prop('disabled', cur === originalQty);
        btnSave.addClass('btn-primary').removeClass('btn-light');
      }
      input.on('input', checkChanged);

      // 5) При смене склада — меняем input + состояние Save
      select.on('change', function(){
        originalQty = parseInt(this.selectedOptions[0].dataset.qty,10);
        input.val(originalQty);
        btnSave.prop('disabled', true);
        btnSave.addClass('btn-light').removeClass('btn-primary');
      });

      // 6) Cancel — просто закрываем дропдаун
      btnCancel.on('click', function(){
        const bs = bootstrap.Dropdown.getOrCreateInstance(btn[0]);
        bs.hide();
      });

      // 7) Save — AJAX и обновление таблицы
      btnSave.on('click', function(){
        const newQty = parseInt(input.val(),10);
        const wid    = parseInt(select.val(),10);
        
        showOverlay();
        $.post('?action=update-product-stock', {
          product_id: pid,
          warehouse_id: wid,
          quantity: newQty
        }, function(res){
          location.reload();
        }, 'json').fail(function(xhr, textStatus, error){
          alert('Серверная ошибка: ' + xhr.statusText);
          hideOverlay();
        });
        
      });

    }, 'json').fail(()=>{
      container.html('<div class="text-danger">Ошибка загрузки</div>');
    });
  });

   // Обработчик удаления товара
  $(document).on('click', '.delete-product', function(e){
    e.preventDefault();
    const pid = $(this).data('product-id');
    if (!confirm(`Удалить товар?`)) return;

    showOverlay(); // если вы используете оверлей

    $.post('?action=delete-product', { product_id: pid }, function(res){
      if (res.success) {
        location.reload();
      } else {
        alert(res.message || 'Ошибка удаления товара');
        hideOverlay();
        location.reload();
      }
    }, 'json')
    .fail(function(){
      alert('Серверная ошибка при удалении');
      hideOverlay();
    });
  });

  // Открытие модалки редактирования товара
  $(document).on('click', '.edit-product', function(e){
    e.preventDefault();
    const pid = $(this).data('product-id');
    // Очищаем существующий список и результаты поиска
    $('#set-table-body').empty();
    $('#set-search-input').val('');
    $('#set-search-results').hide().empty();

    // Загружаем товар
    $.getJSON(`?action=get-product&product_id=${pid}`)
      .done(function(p){
        // Основные поля
        $('#prod-id').val(p.id);
        $('#prod-offer').val(p.offer_id);
        $('#prod-name').val(p.name);
        $('#prod-cost').val(p.cost);
        $('#prod-length').val(p.length);
        $('#prod-width').val(p.width);
        $('#prod-height').val(p.height);
        $('#prod-weight').val(p.weight);
        // Состав набора
        (p.set||[]).forEach(item => {
          addSetRow(item.component_id, item.component_name, item.quantity);
        });
        $('#productModal').modal('show');
      })
      .fail(function(){
        alert('Ошибка при загрузке данных товара');
      });
  });

  const $form      = $('#product-form');
  const $saveBtn   = $('#btn-save-product');

  // При открытии модалки — блокируем Save
  $(document).on('show.bs.modal', '#productModal', function(){
    $saveBtn.prop('disabled', true);
    $saveBtn.addClass('btn-light').removeClass('btn-primary');
  });

  // Всякий раз, когда меняется хоть одно поле формы — разблокируем Save
  $form.on('input change', 'input, select', function(){
    $saveBtn.prop('disabled', false);
    $saveBtn.addClass('btn-primary').removeClass('btn-light');
  });
  
  const $modalContent = $('#productModal .modal-content');
  const $results      = $('#set-search-results');

  // Поиск по Enter в поле ввода
  $('#set-search-input').on('keydown', function(e){
    if (e.key !== 'Enter') return;
    e.preventDefault();

    const q = $(this).val().trim();
    if (!q) {
      $results.hide();
      return;
    }

    // AJAX-поиск
    $.getJSON(`?action=search-product&query=${encodeURIComponent(q)}`)
      .done(function(list){
        $results.empty();

        if (!list.length) {
          $results.append('<div class="list-group-item">Ничего не найдено</div>');
        } else {
          list.forEach(item => {
            const $it = $(`
              <a href="#" class="list-group-item list-group-item-action">
                ${item.offer_id} — ${item.name}
              </a>
            `).data('item', item);
            $results.append($it);
          });
        }
      })
      .fail(function(){
        $results.empty().append('<div class="list-group-item text-danger">Ошибка поиска</div>');
      })
      .always(function(){
        // портируем контейнер внутрь модалки и позиционируем
        $results
          .appendTo($modalContent)
          .show();

        // вычисляем координаты поля ввода относительно modal-content
        const $inp = $('#set-search-input');
        const inpOff = $inp.offset();
        const modOff = $modalContent.offset();

        const top  = inpOff.top  - modOff.top + $inp.outerHeight();
        const left = inpOff.left - modOff.left;
        const width = $inp.outerWidth();

        $results.css({
          top:  top + 'px',
          left: left + 'px',
          width: width + 'px'
        });
      });
  });

  // выбор из выпадашки
  $modalContent.on('click', '#set-search-results .list-group-item-action', function(e){
    e.preventDefault();
    const item = $(this).data('item');
    if (item) {
      addSetRow(item.id, `${item.offer_id} — ${item.name}`, 1);
    }
    $results.hide();
    $('#set-search-input').val('');
  });

  // прятать по клику вне
  $(document).on('click', function(e){
    if (!$(e.target).closest('#set-search-input').length &&
        !$(e.target).closest('#set-search-results').length) {
      $results.hide();
    }
  });

  // Удаление строки набора
  $(document).on('click', '.remove-set-item', function(){
    $(this).closest('tr').remove();
  });

  // Сохранение формы редактирования товара
  $('#product-form').on('submit', function(e){
    e.preventDefault();
    // Собираем данные товара
    const data = {};
    $(this).serializeArray().forEach(i => {
      const m = i.name.match(/product\[(.+)\]/);
      if (m) data[m[1]] = i.value;
    });

    // Собираем массив компонентов
    const setItems = [];
    $('#set-table-body tr').each(function(){
      const compId = $(this).data('comp-id');
      const qty    = parseInt($(this).find('.set-qty-input').val(), 10) || 1;
      setItems.push({ component_id: +compId, quantity: qty });
    });

    // Отправляем AJAX
    $.post('?action=update-product', {
      product: data,
      set: JSON.stringify(setItems)
    }, function(res){
      if (res.success) {
        location.reload();
      } else {
        alert(res.message || 'Ошибка при сохранении');
      }
    }, 'json')
    .fail(function(){
      alert('Серверная ошибка при сохранении');
    });
  });

  // Вспомогательная функция — создание строки в tbody
  function addSetRow(compId, compText, qty) {
    // Не дублируем строку
    if ($('#set-table-body').find(`tr[data-comp-id="${compId}"]`).length) return;
    const $tr = $(`
      <tr data-comp-id="${compId}">
        <td>${compText}</td>
        <td style="width:100px">
          <input type="number" class="form-control form-control-sm set-qty-input" value="${qty}" min="1">
        </td>
        <td style="width:40px; text-align:center">
          <button type="button" class="btn-close remove-set-item" aria-label="Удалить"></button>
        </td>
      </tr>
    `);
    $('#set-table-body').append($tr);
  }
  
});
