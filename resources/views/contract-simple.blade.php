<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Формирование договора</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .section:last-child { border-bottom: none; }
        .section h3 { color: #333; margin-bottom: 15px; }
        button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #005a87; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .error { background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-top: 10px; display: none; }
        .success { background: #efe; color: #363; padding: 10px; border-radius: 4px; margin-top: 10px; display: none; }
        .loading { text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Формирование договора</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">Заполните форму для создания договора</p>

        <form id="contractForm">
            <div class="section">
                <h3>Личные данные</h3>
                <div class="form-group">
                    <label for="client_full_name">ФИО *</label>
                    <input type="text" id="client_full_name" name="client_full_name" value="Новиков Алексей Алексеевич" required>
                </div>
                <div class="form-group">
                    <label for="passport_full">Паспорт (серия номер) *</label>
                    <input type="text" id="passport_full" name="passport_full" value="1234 21341235234" required>
                </div>
                <div class="form-group">
                    <label for="inn">ИНН *</label>
                    <input type="text" id="inn" name="inn" value="1235123512341235" required>
                </div>
                <div class="form-group">
                    <label for="client_address">Адрес *</label>
                    <input type="text" id="client_address" name="client_address" value="Алматы Тестовая 43к5" required>
                </div>
            </div>

            <div class="section">
                <h3>Банковские реквизиты</h3>
                <div class="form-group">
                    <label for="bank_name">Название банка *</label>
                    <input type="text" id="bank_name" name="bank_name" value="Каспи" required>
                </div>
                <div class="form-group">
                    <label for="bank_account">Расчетный счет *</label>
                    <input type="text" id="bank_account" name="bank_account" value="12351234123512341235" required>
                </div>
                <div class="form-group">
                    <label for="bank_bik">БИК *</label>
                    <input type="text" id="bank_bik" name="bank_bik" value="12341235123412351235" required>
                </div>
                <div class="form-group">
                    <label for="swift">SWIFT</label>
                    <input type="text" id="swift" name="swift" value="">
                </div>
            </div>

            <button type="submit" id="submitBtn">Сформировать договор</button>
        </form>

        <div id="error" class="error"></div>
        <div id="success" class="success"></div>
        <div id="loading" class="loading" style="display: none;">Формирование договора...</div>
    </div>

    <script>
        document.getElementById('contractForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const errorDiv = document.getElementById('error');
            const successDiv = document.getElementById('success');
            const loadingDiv = document.getElementById('loading');
            
            // Hide previous messages
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            loadingDiv.style.display = 'block';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                
                console.log('Sending data:', data);
                
                const response = await fetch('/api/contract/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('Response data:', result);
                
                if (result.success) {
                    successDiv.innerHTML = `Договор успешно сформирован! <a href="${result.contract_url}" target="_blank" style="color: #007cba;">Скачать PDF</a>`;
                    successDiv.style.display = 'block';
                } else {
                    errorDiv.textContent = result.message || 'Произошла ошибка при формировании договора';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                console.error('Error:', err);
                errorDiv.textContent = 'Ошибка сети: ' + err.message;
                errorDiv.style.display = 'block';
            } finally {
                loadingDiv.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
