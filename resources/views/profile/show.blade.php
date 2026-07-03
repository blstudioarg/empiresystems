@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="content-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Mi perfil</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('profile.avatar.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="avatar">Foto de perfil</label>
                                <img id="avatar-preview" class="d-block mb-2 rounded-circle" style="max-height: 80px;" src="{{ $user->avatarUrl() }}" alt="">
                                <input type="file" class="form-control @error('avatar') is-invalid @enderror" id="avatar" name="avatar" accept="image/*">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">JPG, PNG o similar, máximo 2 MB.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('avatar').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        document.getElementById('avatar-preview').src = URL.createObjectURL(file);
    });
</script>
@endpush
