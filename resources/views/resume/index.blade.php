@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-file-pdf"></i> Resume PDF Parser</h4>
                    <p class="mb-0 text-muted">Upload your resume in PDF format and auto-fill the form below</p>
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
                        </div>
                    @endif

                    <!-- File Upload Form -->
                    <form action="{{ route('resume.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group mb-3">
                                    <label for="resume" class="form-label">
                                        <strong>Select Resume PDF File</strong>
                                    </label>
                                    <input type="file" 
                                           class="form-control @error('resume') is-invalid @enderror" 
                                           id="resume" 
                                           name="resume" 
                                           accept=".pdf"
                                           required>
                                    @error('resume')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Supported format: PDF (max 10MB). Ensure your PDF contains readable text.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="uploadBtn">
                                    <i class="fas fa-upload"></i> Upload & Extract
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Progress indicator -->
                    <div class="progress mt-3" id="progressBar" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%">
                            Processing PDF... Please wait
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="mt-4">
                        <h5><i class="fas fa-lightbulb"></i> How it works:</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center p-3">
                                    <i class="fas fa-file-upload fa-2x text-primary mb-2"></i>
                                    <h6>1. Upload PDF</h6>
                                    <p class="text-muted small">Select your resume in PDF format</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3">
                                    <i class="fas fa-cogs fa-2x text-success mb-2"></i>
                                    <h6>2. Auto Extract</h6>
                                    <p class="text-muted small">AI extracts text and parses information</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3">
                                    <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                                    <h6>3. Review & Edit</h6>
                                    <p class="text-muted small">Review and edit the auto-filled form</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sample Output Preview -->
                    <div class="mt-4">
                        <h5><i class="fas fa-eye"></i> What will be extracted:</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user text-primary"></i> Personal Information</span>
                                        <small class="text-muted">Name, Email, Phone</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-map-marker-alt text-success"></i> Address</span>
                                        <small class="text-muted">Location details</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-file-alt text-info"></i> Summary</span>
                                        <small class="text-muted">Professional summary</small>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-tools text-warning"></i> Skills</span>
                                        <small class="text-muted">Technical & soft skills</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-briefcase text-danger"></i> Experience</span>
                                        <small class="text-muted">Work history & roles</small>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-graduation-cap text-secondary"></i> Education</span>
                                        <small class="text-muted">Academic qualifications</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.progress {
    height: 25px;
}

.list-group-item {
    border: none;
    border-bottom: 1px solid #dee2e6;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    transform: translateY(-1px);
}
</style>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('resume');
    const progressBar = document.getElementById('progressBar');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a PDF file to upload.');
        return;
    }
    
    // Check file type
    const file = fileInput.files[0];
    if (file.type !== 'application/pdf') {
        e.preventDefault();
        alert('Please select a PDF file only.');
        return;
    }
    
    // Check file size (10MB = 10 * 1024 * 1024 bytes)
    if (file.size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('File size must be less than 10MB.');
        return;
    }
    
    // Show progress bar and disable button
    progressBar.style.display = 'block';
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});

// File input change event to show file name
document.getElementById('resume').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        
        // Create or update file info display
        let fileInfo = document.getElementById('fileInfo');
        if (!fileInfo) {
            fileInfo = document.createElement('div');
            fileInfo.id = 'fileInfo';
            fileInfo.className = 'mt-2 p-2 bg-light rounded';
            e.target.parentNode.appendChild(fileInfo);
        }
        
        fileInfo.innerHTML = `
            <small class="text-muted">
                <i class="fas fa-file-pdf text-danger"></i> 
                <strong>${fileName}</strong> (${fileSize} MB)
            </small>
        `;
    }
});
</script>
@endsection