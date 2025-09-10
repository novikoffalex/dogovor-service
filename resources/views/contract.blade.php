<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор покупки-продажи виртуальных активов</title>
    <style>
        @page {
            margin: 2cm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            color: #000;
        }
        
        .contract-header {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 20px;
        }
        
        .contract-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .contract-body {
            margin: 30px 0;
            text-align: justify;
        }
        
        .contract-body h3 {
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0 10px 0;
            text-align: left;
        }
        
        .contract-body p {
            margin: 8px 0;
            text-indent: 1.5cm;
        }
        
        .contract-body p:first-child {
            text-indent: 0;
        }
        
        .parties-section {
            margin: 30px 0;
        }
        
        .party-info {
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .party-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-block {
            width: 45%;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 20px 0 5px 0;
            height: 20px;
        }
        
        .bank-details {
            margin: 10px 0;
        }
        
        .crypto-wallets {
            margin: 10px 0;
        }
        
        .wallet-item {
            margin: 5px 0;
        }
        
        .contract-number {
            font-weight: bold;
        }
        
        .client-name {
            white-space: nowrap;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        td {
            padding: 8px;
            vertical-align: top;
            border: 1px solid #ddd;
        }
        
        .operator-column {
            width: 50%;
        }
        
        .client-column {
            width: 50%;
        }
    </style>
</head>
<body>
    <div class="contract-header">
        Договор покупки-продажи виртуальных активов № <span class="contract-number">{{ $contract_number }}</span>
    </div>
    
    <div class="contract-info">
        <p>г. Бишкек</p>
        <p>«{{ now()->format('d') }}» {{ now()->format('F Y') }} г.</p>
    </div>
    
    <div class="contract-body">
        <p><strong>Общество с ограниченной ответственностью «ВТП-Технолоджи»</strong>, именуемое в дальнейшем «Оператор», в лице Генерального директора Коваленко Д.С., действующего на основании Устава, с одной стороны, и <strong>{{ $client_full_name }}</strong>, именуемый(ая) в дальнейшем «Клиент», с другой стороны, заключили настоящий Договор о нижеследующем:</p>
        
        <h3>1. ПРЕДМЕТ ДОГОВОРА</h3>
        <p>1.1. Оператор предоставляет Клиенту услуги по обмену виртуальных активов (криптовалют) на фиатные денежные средства и наоборот.</p>
        <p>1.2. Оператор имеет лицензию на осуществление деятельности по обмену виртуальных активов №116 от 4 Сентября 2024 года, выданную Службой Регулирования и Надзора за Финансовым Рынком при Министерстве Экономики и Коммерции Кыргызской Республики.</p>
        
        <h3>2. ПРАВА И ОБЯЗАННОСТИ СТОРОН</h3>
        <p>2.1. Оператор обязуется:</p>
        <p>- Обеспечить безопасность проведения операций с виртуальными активами;</p>
        <p>- Соблюдать требования законодательства Кыргызской Республики в области противодействия легализации (отмыванию) доходов, полученных преступным путем, и финансированию терроризма;</p>
        <p>- Предоставлять Клиенту информацию о курсах обмена и комиссиях.</p>
        
        <p>2.2. Клиент обязуется:</p>
        <p>- Предоставить достоверные персональные данные и документы;</p>
        <p>- Соблюдать требования Оператора по идентификации и верификации;</p>
        <p>- Не использовать сервис для незаконных операций.</p>
        
        <h3>3. ПОРЯДОК РАСЧЕТОВ</h3>
        <p>3.1. Обмен виртуальных активов производится по курсу, действующему на момент совершения операции.</p>
        <p>3.2. Комиссия за услуги составляет согласно тарифам Оператора.</p>
        <p>3.3. Расчеты производятся в соответствии с заявкой Клиента.</p>
        
        <h3>4. ОТВЕТСТВЕННОСТЬ СТОРОН</h3>
        <p>4.1. Стороны несут ответственность за неисполнение или ненадлежащее исполнение своих обязательств в соответствии с действующим законодательством.</p>
        <p>4.2. Оператор не несет ответственности за убытки, возникшие в результате действий третьих лиц или форс-мажорных обстоятельств.</p>
        
        <h3>5. ЗАКЛЮЧИТЕЛЬНЫЕ ПОЛОЖЕНИЯ</h3>
        <p>5.1. Настоящий Договор вступает в силу с момента его подписания и действует до полного исполнения обязательств сторонами.</p>
        <p>5.2. Все споры решаются путем переговоров, а при невозможности достижения соглашения - в судебном порядке.</p>
        <p>5.3. Изменения и дополнения к настоящему Договору оформляются дополнительными соглашениями.</p>
    </div>
    
    <div class="parties-section">
        <div class="party-info">
            <div class="party-title">Клиент:</div>
            <p><span class="client-name">{{ $client_full_name }}</span></p>
            <p>Паспорт РФ: {{ $passport_full }}</p>
            <p>ИНН: {{ $inn }}</p>
            <p>Адрес: {{ $client_address }}</p>
        </div>
        
        <div class="bank-details">
            <p><strong>Банковские реквизиты:</strong></p>
            <p>Банк: {{ $bank_name }}</p>
            <p>P/c: {{ $bank_account }}</p>
            <p>БИК: {{ $bank_bik }}</p>
            @if(!empty($bank_swift))
            <p>SWIFT: {{ $bank_swift }}</p>
            @endif
        </div>
    </div>
    
    <div class="parties-section">
        <div class="party-info">
            <div class="party-title">Оператор:</div>
            <p>ОсОО "ВТП-Технолоджи"</p>
            <p>Регистрационный номер: 305867-3301-000</p>
            <p>ИНН: 01007202410391</p>
            <p>ОКПО: 33112978</p>
            <p>Юридический адрес: Кыргызская Республика, Бишкек, Первомайский район, пр. Чынгыз Айтматова, 4, Блок И., 54</p>
            <p>Фактический адрес: Кыргызская Республика, Бишкек, Первомайский район, пр. Чынгыз Айтматова, 4, Блок И., 54</p>
        </div>
        
        <div class="bank-details">
            <p><strong>Банковские реквизиты:</strong></p>
            <p>Банки: ОАО «ФинансКредитБанк»</p>
            <p>P/c: 1340000090402674</p>
            <p>БИК: 134001 SWIFT: FIKBKG22</p>
        </div>
        
        <div class="crypto-wallets">
            <p><strong>Криптовалютные кошельки:</strong></p>
            <div class="wallet-item">Адрес кошелька Tron (TRC 20) USDT: TU3uMU6ajuy4jhaxYyX8rKxS9rhCTrk4g6</div>
            <div class="wallet-item">Адрес кошелька Ethereum (ERC-20) USDT: 0x267687Dfbcb6aCAdB0700Ed715ad0eE006592e2c</div>
            <div class="wallet-item">Адрес кошелька ВТС: bc1q7k7up0vu2sknarlawxw44mrh7rprz2dhxp288y</div>
            <div class="wallet-item">Адрес кошелька ЕТН: 0x267687Dfbcb6aCAdB0700Ed715ad0eE006592e2c</div>
        </div>
    </div>
    
    <div class="signature-section">
        <div class="signature-block">
            <p><strong>Клиент:</strong></p>
            <div class="signature-line"></div>
            <p>{{ $client_full_name }}</p>
        </div>
        
        <div class="signature-block">
            <p><strong>Оператор:</strong></p>
            <p>Генеральный директор</p>
            <div class="signature-line"></div>
            <p>Коваленко Д.С.</p>
        </div>
    </div>
</body>
</html>
