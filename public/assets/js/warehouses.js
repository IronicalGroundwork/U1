$(document).ready(function() {
        const template = $('#warehouseFormTemplate').html();
        
        // Добавление формы
        $('#addWarehouseBtn').click(async function() {
            const $form = $(template);
            $('#warehouseFormsContainer').append($form);
            
            try {
                const response = await fetch('index.php?action=get-marketplace-warehouses');
                const data = await response.json();
                
                $form.find('.platform-select').each(function() {
                    const platform = $(this).data('platform');
                    const select = $(this).find('select');
                    select.empty().append('<option value="">Не привязывать</option>');
                    
                    data[platform]?.forEach(wh => {
                        select.append(
                            `<option value="${wh.marketplace_seller_id}_${wh.id}_${wh.name}">
                                ${wh.name} (${wh.id})
                            </option>`
                        );
                    });
                });
                
            } catch (error) {
                alert('Ошибка загрузки данных');
                console.log(error);
            }
        });

        // Удаление формы
        $(document).on('click', '.remove-card', function() {
            $(this).closest('.warehouse-card').remove();
        });

        // Отправка формы
        $(document).on('submit', '.warehouse-form', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            let hasSelection = false;
            $(this).find('select').each(function() {
                if ($(this).val()) hasSelection = true;
            });

            if (!hasSelection) {
                alert('Выберите хотя бы один маркетплейс');
                return;
            }

            try {
                const response = await fetch('index.php?action=save-warehouses', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Ошибка сохранения');
                }
            } catch (error) {
                alert('Ошибка соединения');
            }
        });

        // Удаление склада
        $(document).on('click', '.delete-warehouse', function() {
            const card = $(this).closest('.card');
            const warehouseId = card.data('warehouse-id');

            if (confirm('Удалить склад?')) {
                $.ajax({
                    url: 'index.php?action=delete-warehouses',
                    method: 'POST',
                    data: { warehouse_id: warehouseId },
                    success: function(response) {
                        if (response.success) {
                            card.remove();
                        } else {
                            alert(response.error || 'Ошибка удаления');
                        }
                    },
                    error: function() {
                        alert('Ошибка соединения');
                    }
                });
            }
        });
    });