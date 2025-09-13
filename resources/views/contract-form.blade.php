<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Формирование договора</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        /* Inline critical CSS to reduce external dependencies */
        .form-container { max-width: 48rem; margin: 0 auto; }
        .form-section { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-input { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
        .btn-primary { background-color: #3b82f6; color: white; padding: 0.75rem 1.5rem; border-radius: 0.375rem; border: none; cursor: pointer; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-primary:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .error-message { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 0.75rem; border-radius: 0.375rem; margin-top: 1rem; }
        .success-message { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.75rem; border-radius: 0.375rem; margin-top: 1rem; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4">
        <div class="form-container">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Формирование договора</h1>
                <p class="mt-2 text-gray-600">Заполните форму для создания договора</p>
            </div>

            <!-- Form -->
            <div class="bg-white shadow-lg rounded-lg p-8" x-data="contractForm()">
                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Personal Information -->
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Личные данные</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="client_full_name" class="block text-sm font-medium text-gray-700">ФИО *</label>
                                <input type="text" id="client_full_name" name="client_full_name" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.client_full_name">
                            </div>
                            <div>
                                <label for="passport_full" class="block text-sm font-medium text-gray-700">Паспорт (серия номер) *</label>
                                <input type="text" id="passport_full" name="passport_full" required
                                       placeholder="1234 567890"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.passport_full">
                            </div>
                            <div>
                                <label for="inn" class="block text-sm font-medium text-gray-700">ИНН *</label>
                                <input type="text" id="inn" name="inn" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.inn">
                            </div>
                            <div>
                                <label for="client_address" class="block text-sm font-medium text-gray-700">Адрес *</label>
                                <input type="text" id="client_address" name="client_address" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.client_address">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Information -->
                    <div class="border-b border-gray-200 pb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Банковские реквизиты</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bank_name" class="block text-sm font-medium text-gray-700">Название банка *</label>
                                <input type="text" id="bank_name" name="bank_name" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.bank_name">
                            </div>
                            <div>
                                <label for="bank_account" class="block text-sm font-medium text-gray-700">Расчетный счет *</label>
                                <input type="text" id="bank_account" name="bank_account" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.bank_account">
                            </div>
                            <div>
                                <label for="bank_bik" class="block text-sm font-medium text-gray-700">БИК *</label>
                                <input type="text" id="bank_bik" name="bank_bik" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.bank_bik">
                            </div>
                            <div>
                                <label for="bank_swift" class="block text-sm font-medium text-gray-700">SWIFT</label>
                                <input type="text" id="bank_swift" name="bank_swift"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       x-model="form.bank_swift">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-center">
                        <button type="submit" 
                                :disabled="loading"
                                class="bg-blue-600 text-white px-8 py-3 rounded-md font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!loading">Сформировать договор</span>
                            <span x-show="loading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Формирование...
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Success Message -->
                <div x-show="success" class="mt-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">Договор успешно сформирован!</h3>
                            <div class="mt-2 text-sm text-green-700">
                                <p>Номер договора: <span x-text="contractData.contract_number"></span></p>
                                <div class="mt-3 flex space-x-3">
                                    <a :href="contractData.contract_url" 
                                       class="bg-green-600 text-white px-4 py-2 rounded-md text-sm hover:bg-green-700">
                                        Скачать PDF
                                    </a>
                                    <button @click="showUploadForm = true" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                                        Загрузить подписанный договор
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Form -->
                <div x-show="showUploadForm" class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
                    <h3 class="text-lg font-medium text-blue-900 mb-4">Загрузка подписанного договора</h3>
                    <form @submit.prevent="uploadSignedContract" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="signed_contract" class="block text-sm font-medium text-blue-700 mb-2">
                                Выберите файл (PDF, JPG, PNG)
                            </label>
                            <input type="file" id="signed_contract" name="signed_contract" 
                                   accept=".pdf,.jpg,.jpeg,.png" required
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                   @change="form.signed_contract = $event.target.files[0]">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" 
                                    :disabled="uploading"
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!uploading">Загрузить</span>
                                <span x-show="uploading">Загрузка...</span>
                            </button>
                            <button type="button" @click="showUploadForm = false"
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm hover:bg-gray-400">
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Error Message -->
                <div x-show="error" class="mt-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Ошибка</h3>
                            <div class="mt-2 text-sm text-red-700" x-text="errorMessage"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function contractForm() {
            return {
                form: {
                    client_full_name: '',
                    passport_full: '',
                    inn: '',
                    client_address: '',
                    bank_name: '',
                    bank_account: '',
                    bank_bik: '',
                    bank_swift: '',
                    signed_contract: null
                },
                loading: false,
                uploading: false,
                success: false,
                error: false,
                showUploadForm: false,
                contractData: {},
                errorMessage: '',

                async submitForm() {
                    this.loading = true;
                    this.error = false;
                    this.success = false;

                    try {
                        console.log('Sending request with data:', this.form);
                        const response = await fetch('/api/contract/generate', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        console.log('Response data:', data);

                        if (data.success) {
                            this.contractData = data;
                            this.success = true;
                        } else {
                            this.error = true;
                            this.errorMessage = data.message || 'Произошла ошибка при формировании договора';
                        }
                    } catch (err) {
                        console.error('Request error:', err);
                        this.error = true;
                        this.errorMessage = 'Ошибка сети: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async uploadSignedContract() {
                    this.uploading = true;
                    this.error = false;

                    const formData = new FormData();
                    formData.append('signed_contract', this.form.signed_contract);
                    formData.append('contract_number', this.contractData.contract_number);

                    try {
                        const response = await fetch('/api/contract/upload-signed', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            alert('Подписанный договор успешно загружен!');
                            this.showUploadForm = false;
                        } else {
                            this.error = true;
                            this.errorMessage = data.message || 'Ошибка при загрузке файла';
                        }
                    } catch (err) {
                        this.error = true;
                        this.errorMessage = 'Ошибка сети. Попробуйте еще раз.';
                    } finally {
                        this.uploading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
