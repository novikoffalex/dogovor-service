<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Договор</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { text-align: center; color: #333; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .error { background: #fee; color: #c33; padding: 10px; margin: 10px 0; border-radius: 3px; display: none; }
        .success { background: #efe; color: #363; padding: 10px; margin: 10px 0; border-radius: 3px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Формирование договора</h1>
        
        <form id="form">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="client_full_name" value="Новиков Алексей Алексеевич" required>
            </div>
            
            <div class="form-group">
                <label>Паспорт *</label>
                <input type="text" name="passport_full" value="1234 21341235234" required>
            </div>
            
            <div class="form-group">
                <label>ИНН *</label>
                <input type="text" name="inn" value="1235123512341235" required>
            </div>
            
            <div class="form-group">
                <label>Адрес *</label>
                <input type="text" name="client_address" value="Алматы Тестовая 43к5" required>
            </div>
            
            <div class="form-group">
                <label>Банк *</label>
                <input type="text" name="bank_name" value="Каспи" required>
            </div>
            
            <div class="form-group">
                <label>Счет *</label>
                <input type="text" name="bank_account" value="12351234123512341235" required>
            </div>
            
            <div class="form-group">
                <label>БИК *</label>
                <input type="text" name="bank_bik" value="12341235123412351235" required>
            </div>
            
            <div class="form-group">
                <label>SWIFT</label>
                <input type="text" name="bank_swift" value="">
            </div>
            
            <button type="submit">Сформировать договор</button>
        </form>
        
        <div id="error" class="error"></div>
        <div id="success" class="success"></div>
    </div>

    <script>
        document.getElementById('form').onsubmit = async function(e) {
            e.preventDefault();
            
            const btn = document.querySelector('button');
            const error = document.getElementById('error');
            const success = document.getElementById('success');
            
            btn.disabled = true;
            error.style.display = 'none';
            success.style.display = 'none';
            
            try {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                
                const response = await fetch('/api/contract/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    success.innerHTML = 'Договор готов! <a href="' + result.contract_url + '" target="_blank">Скачать DOCX</a>';
                    success.style.display = 'block';
                } else {
                    error.textContent = result.message || 'Ошибка';
                    error.style.display = 'block';
                }
            } catch (err) {
                error.textContent = 'Ошибка сети: ' + err.message;
                error.style.display = 'block';
            } finally {
                btn.disabled = false;
            }
        };
    </script>
</body>
</html>

