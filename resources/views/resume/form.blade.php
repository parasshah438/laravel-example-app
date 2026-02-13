@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-magic"></i> Auto-Filled Resume Information</h4>
                        <p class="mb-0 text-muted">
                            <i class="fas fa-file-pdf"></i> 
                            Extracted from: <strong>{{ $fileName ?? 'resume.pdf' }}</strong>
                        </p>
                    </div>
                    <a href="{{ route('resume.index') }}" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Upload Another
                    </a>
                </div>

                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            @if(session('saved_data'))
                                <br><small>Data has been processed successfully!</small>
                            @endif
                        </div>
                    @endif

                    <!-- Extraction Status with Debug Info -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Extraction Complete!</strong> 
                        @if(isset($debugInfo))
                            <br>
                            <small class="text-muted">
                                📊 <strong>{{ $debugInfo['total_characters'] }}</strong> characters, 
                                <strong>{{ $debugInfo['total_lines'] }}</strong> lines, 
                                <strong>{{ $debugInfo['words_found'] }}</strong> words extracted
                                <br>
                                📋 Preview: <em>"{{ $debugInfo['extraction_preview'] }}"</em>
                            </small>
                        @endif
                        <br>
                        <small>Please review and edit the auto-filled information below. Fields may need manual adjustment for accuracy.</small>
                    </div>

                    <!-- Resume Form -->
                    <form action="{{ route('resume.save') }}" method="POST" id="resumeForm">
                        @csrf
                        
                        <!-- Personal Information Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="name" class="form-label">
                                                <strong>Full Name *</strong>
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" 
                                                   name="name" 
                                                   value="{{ old('name', $data['name'] ?? '') }}" 
                                                   required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="email" class="form-label">
                                                <strong>Email Address *</strong>
                                            </label>
                                            <input type="email" 
                                                   class="form-control @error('email') is-invalid @enderror" 
                                                   id="email" 
                                                   name="email" 
                                                   value="{{ old('email', $data['email'] ?? '') }}" 
                                                   required>
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="phone" class="form-label">
                                                <strong>Phone Number</strong>
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('phone') is-invalid @enderror" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="{{ old('phone', $data['phone'] ?? '') }}">
                                            @error('phone')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="address" class="form-label">
                                                <strong>Address</strong>
                                            </label>
                                            <input type="text" 
                                                   class="form-control @error('address') is-invalid @enderror" 
                                                   id="address" 
                                                   name="address" 
                                                   value="{{ old('address', $data['address'] ?? '') }}">
                                            @error('address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Summary Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Professional Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="summary" class="form-label">
                                        <strong>Summary/Objective</strong>
                                    </label>
                                    <textarea class="form-control @error('summary') is-invalid @enderror" 
                                              id="summary" 
                                              name="summary" 
                                              rows="4" 
                                              placeholder="Professional summary or career objective">{{ old('summary', $data['summary'] ?? '') }}</textarea>
                                    @error('summary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Skills Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-tools"></i> Skills & Expertise</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="skills" class="form-label">
                                        <strong>Technical Skills</strong>
                                    </label>
                                    <textarea class="form-control @error('skills') is-invalid @enderror" 
                                              id="skills" 
                                              name="skills" 
                                              rows="3" 
                                              placeholder="List your technical skills, tools, and technologies">{{ old('skills', $data['skills'] ?? '') }}</textarea>
                                    @error('skills')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Work Experience Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-briefcase"></i> Work Experience</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="experience" class="form-label">
                                        <strong>Professional Experience</strong>
                                    </label>
                                    <textarea class="form-control @error('experience') is-invalid @enderror" 
                                              id="experience" 
                                              name="experience" 
                                              rows="6" 
                                              placeholder="List your work experience, positions held, and achievements">{{ old('experience', $data['experience'] ?? '') }}</textarea>
                                    @error('experience')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Education Section -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Education</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="education" class="form-label">
                                        <strong>Educational Background</strong>
                                    </label>
                                    <textarea class="form-control @error('education') is-invalid @enderror" 
                                              id="education" 
                                              name="education" 
                                              rows="4" 
                                              placeholder="List your educational qualifications, degrees, and certifications">{{ old('education', $data['education'] ?? '') }}</textarea>
                                    @error('education')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#rawTextModal">
                                <i class="fas fa-eye"></i> View Raw Extracted Text
                            </button>
                            
                            <div>
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="clearForm()">
                                    <i class="fas fa-eraser"></i> Clear All
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Save Information
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Raw Text Modal with Enhanced Debug -->
<div class="modal fade" id="rawTextModal" tabindex="-1" aria-labelledby="rawTextModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Made it larger for better viewing -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rawTextModalLabel">
                    <i class="fas fa-file-alt"></i> Raw Extracted Text & Debug Info
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(isset($debugInfo))
                    <div class="alert alert-light border">
                        <h6><i class="fas fa-info-circle"></i> Extraction Statistics:</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Total Characters:</strong> {{ number_format($debugInfo['total_characters']) }}
                            </div>
                            <div class="col-md-3">
                                <strong>Total Lines:</strong> {{ number_format($debugInfo['total_lines']) }}
                            </div>
                            <div class="col-md-3">
                                <strong>Word Count:</strong> {{ number_format($debugInfo['words_found']) }}
                            </div>
                            <div class="col-md-3">
                                <strong>File:</strong> {{ $fileName ?? 'Unknown' }}
                            </div>
                        </div>
                    </div>
                @endif
                
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb"></i> 
                    <strong>Enhanced Extraction:</strong> This text was extracted using multiple methods including header content, metadata, and form fields.
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><strong>Raw Extracted Content:</strong></label>
                    <div class="position-relative">
                        <pre class="bg-light p-3 rounded border" 
                             style="max-height: 500px; overflow-y: auto; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.85rem; line-height: 1.4;"
                             id="rawTextContent">{{ $extractedText ?? 'No text extracted' }}</pre>
                        <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" 
                                onclick="searchInText()" title="Search in text">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Search functionality -->
                <div class="mb-3">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchText" placeholder="Search in extracted text...">
                        <button class="btn btn-outline-primary" type="button" onclick="highlightText()">
                            <i class="fas fa-search"></i> Highlight
                        </button>
                        <button class="btn btn-outline-secondary" type="button" onclick="clearHighlights()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                
                <!-- Text analysis -->
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-chart-line"></i> First 10 Lines Preview:</h6>
                        <pre class="bg-secondary text-white p-2 rounded small">{{ implode("\n", array_slice(explode("\n", $extractedText ?? ''), 0, 10)) }}</pre>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-tools"></i> Quick Analysis:</h6>
                        <ul class="list-unstyled small">
                            <li><i class="fas fa-at text-primary"></i> Email found: {{ preg_match('/@/', $extractedText ?? '') ? 'Yes' : 'No' }}</li>
                            <li><i class="fas fa-phone text-success"></i> Phone found: {{ preg_match('/\d{3,}/', $extractedText ?? '') ? 'Yes' : 'No' }}</li>
                            <li><i class="fas fa-user text-info"></i> Potential names: {{ min(3, preg_match_all('/\b[A-Z][a-z]+\s+[A-Z][a-z]+\b/', $extractedText ?? '', $matches)) }}</li>
                            <li><i class="fas fa-briefcase text-warning"></i> Experience section: {{ preg_match('/experience|work|employment/i', $extractedText ?? '') ? 'Found' : 'Not found' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="copyToClipboard()">
                    <i class="fas fa-copy"></i> Copy All Text
                </button>
                <button type="button" class="btn btn-success" onclick="downloadText()">
                    <i class="fas fa-download"></i> Download as TXT
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.card-header {
    font-weight: 600;
}

.form-label {
    color: #495057;
    font-weight: 500;
}

.card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
}

textarea {
    resize: vertical;
}

pre {
    font-size: 0.875rem;
    line-height: 1.4;
}
</style>

<script>
function clearForm() {
    if (confirm('Are you sure you want to clear all form data?')) {
        document.getElementById('resumeForm').reset();
    }
}

function copyToClipboard() {
    const textArea = document.createElement('textarea');
    textArea.value = document.querySelector('#rawTextContent').textContent;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    
    // Show feedback
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-success');
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-primary');
    }, 2000);
}

function downloadText() {
    const text = document.querySelector('#rawTextContent').textContent;
    const blob = new Blob([text], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'extracted_resume_text.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function searchInText() {
    const searchTerm = prompt('Enter text to search for:');
    if (searchTerm) {
        document.getElementById('searchText').value = searchTerm;
        highlightText();
    }
}

function highlightText() {
    const searchTerm = document.getElementById('searchText').value;
    if (!searchTerm) return;
    
    const textElement = document.getElementById('rawTextContent');
    let content = textElement.textContent;
    
    // Clear previous highlights
    clearHighlights();
    
    // Create regex for case-insensitive search
    const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    
    // Replace matches with highlighted version
    const highlightedContent = content.replace(regex, '<mark class="bg-warning">$1</mark>');
    
    // Update content
    textElement.innerHTML = highlightedContent;
    
    // Scroll to first match
    const firstMatch = textElement.querySelector('mark');
    if (firstMatch) {
        firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function clearHighlights() {
    const textElement = document.getElementById('rawTextContent');
    const originalText = textElement.textContent || textElement.innerText;
    textElement.textContent = originalText;
}

// Auto-save functionality (optional)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resumeForm');
    const inputs = form.querySelectorAll('input, textarea');
    
    // Add change listeners to auto-highlight modified fields
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.add('border-warning');
            setTimeout(() => {
                this.classList.remove('border-warning');
            }, 1000);
        });
    });
    
    // Character counters for textareas
    const textareas = form.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const label = textarea.previousElementSibling;
        const maxLength = textarea.getAttribute('maxlength');
        
        if (maxLength) {
            const counter = document.createElement('small');
            counter.className = 'text-muted float-end';
            label.appendChild(counter);
            
            function updateCounter() {
                const remaining = maxLength - textarea.value.length;
                counter.textContent = `${remaining} characters remaining`;
                counter.className = remaining < 100 ? 'text-danger float-end' : 'text-muted float-end';
            }
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }
    });
    
    // Auto-expand textareas
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 300) + 'px';
        });
        
        // Initial resize
        if (textarea.value.length > 0) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 300) + 'px';
        }
    });
});
</script>
@endsection