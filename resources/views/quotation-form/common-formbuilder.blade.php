@extends(config('ogformbuilder.layout'))

@section('title', $isFormBuilder ?? false ? 'Form Builder' : 'Quotation Form')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/formBuilder@3.9.7/dist/form-builder.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/formBuilder@3.9.7/dist/form-render.min.css">
<style>
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    .card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 20px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-weight: 500;
        margin-bottom: 8px;
        color: #333;
    }
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .radio-group {
        display: flex;
        gap: 20px;
        padding: 10px 0;
    }
    .radio-label {
        margin-left: 5px;
        color: #555;
    }
    .error-message {
        color: #dc3545;
        font-size: 14px;
        margin-top: 5px;
    }
    #fb-editor {
        min-height: 500px;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        margin: 20px 0;
        background: #fff;
        padding: 20px;
    }
    .btn {
        padding: 8px 16px;
        border-radius: 4px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        cursor: pointer;
        border: none;
    }
    .btn-primary {
        background: #007bff;
        border: 1px solid #0056b3;
        color: white;
    }
    .btn-primary:hover {
        background: #0056b3;
    }
    .btn-info {
        background: #17a2b8;
        border: 1px solid #138496;
        color: white;
    }
    .btn-secondary {
        background: #6c757d;
        border: 1px solid #545b62;
        color: white;
    }
    .btn-secondary:hover {
        background: #545b62;
        color: white;
    }
    .page-header {
        margin-bottom: 20px;
    }
    .page-title {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 15px;
    }
    .badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
    .badge-master {
        background: #6610f2;
        color: white;
    }
    .badge-company {
        background: #28a745;
        color: white;
    }
    .d-flex {
        display: flex;
    }
    .justify-content-between {
        justify-content: space-between;
    }
    .align-items-center {
        align-items: center;
    }
    .gap-2 {
        gap: 0.5rem;
    }
    .mb-4 {
        margin-bottom: 1.5rem;
    }
    .d-inline {
        display: inline;
    }
    .alert {
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    .frmb-control li {
        padding: 8px 12px !important;
        margin: 4px !important;
        border-radius: 4px !important;
    }
    .form-field {
        padding: 15px !important;
        margin: 10px 0 !important;
        border: 1px solid #e0e0e0 !important;
        border-radius: 6px !important;
        background: #fafafa !important;
    }
    .form-field:hover {
        background: #f0f0f0 !important;
    }
</style>
@endpush

@section('content')
    <div class="container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title d-inline">Quotation Form</h1>
                    @if($isCompany)
                        <span class="badge badge-company">COMPANY OVERRIDE</span>
                    @else
                        <span class="badge badge-master">MASTER</span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($isCompany || !($isFormBuilder ?? false))
                    <a href="{{ route('settings.quotation-form.reset') }}" 
                       class="btn btn-secondary"
                       onclick="return confirm('Reset to super admin default? This will delete your company form and use the master form.')">
                        <i class="fas fa-undo"></i> Reset to Default
                    </a>
                    @endif
                    @if($isFormBuilder ?? false)
                    <a href="{{ route('formbuilder.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Forms
                    </a>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form id="form-builder-form" method="POST" action="{{ $action }}">
                @csrf
                @if($isNew == false)
                    <input type="hidden" name="_method" value="PUT">
                @endif
                
                <div id="form-error-msg" class="error-message" style="display:none;"></div>

                @if(!empty($isNew) && $isNew === false)
                    <input type="hidden" name="title" value="{{ old('title', $form->title ?? '') }}">
                @else
                <div class="form-group">
                    <label class="form-label" for="form-title">Form Title</label>
                    <input type="text" name="title" class="form-control" id="form-title"
                        value="{{ old('title', $form->title ?? '') }}">
                    @error('title')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                @endif

                @php
                    $authUser = \Illuminate\Support\Facades\Auth::user();
                    $isSuperAdmin = $authUser->isSuperAdmin() ?? false;
                    $isFormBuilderPage = $isFormBuilder ?? false;
                    
                    // Only show company assignment for super admin in form builder
                    $showCompanyAssignment = $isSuperAdmin && $isFormBuilderPage;
                    
                    $allCompanies = [];
                    if ($showCompanyAssignment) {
                        $allCompanies = \App\Models\Company::with('users')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(function ($company) {
                                return [$company->id => $company->name];
                            })
                            ->toArray();
                    }
                    
                    // Get selected company IDs safely
                    $selectedCompanyIds = [];
                    if (isset($form->companies) && is_object($form->companies) && method_exists($form->companies, 'pluck')) {
                        $selectedCompanyIds = $form->companies->pluck('id')->toArray() ?? [];
                    }
                @endphp

                @if($showCompanyAssignment && count($allCompanies) > 0)
                <div class="form-group">
                    <label class="form-label" for="company_ids">Assign to Companies <small class="text-muted">(Select multiple)</small></label>
                    <select name="company_ids[]" id="company_ids" class="form-control" multiple size="4">
                        @foreach($allCompanies as $id => $name)
                            <option value="{{ $id }}" {{ in_array($id, old('company_ids', $selectedCompanyIds)) ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple companies</small>
                    @error('company_ids')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                @endif

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <div>
                            <input type="radio" id="status-active" name="status" value="active" {{ ($form->status ?? 'inactive') === 'active' ? 'checked' : '' }}>
                            <label class="radio-label" for="status-active">Active</label>
                        </div>
                        <div>
                            <input type="radio" id="status-inactive" name="status" value="inactive" {{ ($form->status ?? 'inactive') === 'inactive' ? 'checked' : '' }}>
                            <label class="radio-label" for="status-inactive">Inactive</label>
                        </div>
                    </div>
                </div>

                <textarea id="form-json" name="form_json" hidden></textarea>

                <div id="fb-editor"></div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Form
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/formBuilder@3.9.7/dist/form-builder.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editorElement = document.getElementById('fb-editor');
            const baseAssetUrl = "{{ route('formbuilder.assets.image', '') }}/";
            let hasCaptchaError = false;

            const formData = @json($form->fields ?? []);

            const fbEditor = jQuery(editorElement).formBuilder({
                disabledActionButtons: ['data', 'save', 'clear'],
                fieldRemoveWarn: true,
                formData: formData,
                controlOrder: [
                    'text',
                    'number',
                    'textarea',
                    'select',
                    'checkbox-group',
                    'radio-group',
                    'file',
                    'date',
                    'hidden',
                    'captcha',
                    'button',
                ],
                templates: {
                    file: function(fieldData) {
                        return {
                            field: '<input type="file" class="' + (fieldData.className || 'form-control') + '" name="' + fieldData.name + '" id="' + fieldData.id + '" />'
                        };
                    },
                    captcha: function(fieldData) {
                        const subtype = fieldData.subtype || 'hcaptcha';
                        let html = '';
                        if (subtype === 'hcaptcha') {
                            html = `
                                <div class="captcha-wrapper">
                                    <p><strong>hCaptcha</strong></p>
                                    <div style="background:#f2f2f2;padding:10px;border:1px solid #ccc;">
                                    <img src="${baseAssetUrl}hcaptcha-icon.jpeg" alt="hCaptcha" style="margin-bottom:5px;" />
                                    </div>
                                </div>`;
                        } else if (subtype === 'recaptcha') {
                            html = `
                                <div class="captcha-wrapper">
                                    <p><strong>reCAPTCHA</strong></p>
                                    <div style="background:#f9f9f9;padding:10px;border:1px solid:#aaa;">
                                    <img src="${baseAssetUrl}recaptcha-icon.png" alt="reCaptcha" style="margin-bottom:5px;" />
                                    </div>
                                </div>`;
                        }
                        return { field: html };
                    }
                },
                fields: [{
                    label: 'Captcha',
                    type: 'captcha',
                    icon: '🔒',
                    className: 'captcha-control-item'
                }],
                typeUserAttrs: {
                    captcha: {
                        subtype: {
                            label: 'Captcha Type',
                            options: {
                                hcaptcha: 'hCaptcha',
                                recaptcha: 'reCAPTCHA'
                            },
                            value: 'hcaptcha'
                        }
                    }
                },
                onOpenFieldEdit: function(editPanel) {
                    setTimeout(() => {
                        const typeInput = editPanel.querySelector('select[name="subtype"]');
                        if (typeInput && typeInput.value === 'textarea') {
                            const allGroups = editPanel.querySelectorAll('.form-group');
                            allGroups.forEach(group => {
                                const label = group.querySelector('label');
                                if (label && label.textContent.trim().toLowerCase() === 'type') {
                                    group.style.display = 'none';
                                }
                            });
                        }
                    }, 50);
                },
                onAddField: function(_, fieldData) {
                    const errorMsg = document.getElementById('form-error-msg');
                    setTimeout(() => {
                        if (fieldData.type === 'captcha') {
                            let captchaFields = [];
                            try {
                                const currentFieldsJson = fbEditor.actions.getData('json');
                                const currentFields = JSON.parse(currentFieldsJson);
                                captchaFields = currentFields.filter(f => f.type === 'captcha');
                            } catch (e) {
                                console.warn('Could not parse form data:', e);
                            }

                            if (captchaFields.length > 1) {
                                document.querySelectorAll('.frmb-control li').forEach(function(li) {
                                    if (li.dataset.type === 'captcha') {
                                        li.style.display = 'none';
                                    }
                                });

                                if (errorMsg) {
                                    hasCaptchaError = true;
                                    setTimeout(() => {
                                        errorMsg.textContent = 'Heads Up! Only one Captcha field is ideal. Please keep only one and delete the rest.';
                                        errorMsg.style.display = 'block';
                                        errorMsg.scrollIntoView({
                                            behavior: 'smooth',
                                            block: 'center'
                                        });
                                    }, 10);
                                }
                            } else {
                                hasCaptchaError = false;
                                if (errorMsg) {
                                    errorMsg.textContent = '';
                                    errorMsg.style.display = 'none';
                                }
                            }
                        }
                        const stageWrap = document.querySelector('.form-wrap.form-builder .stage-wrap');
                        if (stageWrap) {
                            stageWrap.style.border = '3px dashed #ccc';
                        }
                    }, 100);
                }
            });

            const form = document.getElementById('form-builder-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const titleInput = form.querySelector('input[name="title"]');
                const jsonField = document.getElementById('form-json');
                const formBuilderData = fbEditor.actions.getData('json');
                const parsedData = JSON.parse(formBuilderData);
                const errorMsg = document.getElementById('form-error-msg');

                titleInput.style.border = '';
                const emptyStage = document.querySelector('.form-wrap.form-builder .stage-wrap.empty');
                if (emptyStage) {
                    emptyStage.style.border = '';
                }

                if (!titleInput.value.trim()) {
                    errorMsg.textContent = 'Please enter a form title.';
                    errorMsg.style.display = 'block';
                    titleInput.style.border = '2px solid red';
                    titleInput.focus();
                    return;
                }

                if (!parsedData.length) {
                    errorMsg.textContent = 'Please add at least one field before saving.';
                    errorMsg.style.display = 'block';
                    if (emptyStage) {
                        emptyStage.style.border = '3px dashed red';
                    }
                    return;
                }

                errorMsg.style.display = 'none';
                jsonField.value = formBuilderData;
                form.submit();
            });
        });
    </script>
@endpush
