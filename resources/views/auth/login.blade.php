<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Harent Vendor Comparison</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f7ff;
        }

        .login-card {
            max-width: 420px;
            margin: 100px auto;
        }

        .brand-header {
            background: #6d28d9;
            color: #fff;
            border-radius: .75rem .75rem 0 0;
            padding: 2rem;
            text-align: center;
        }

        .brand-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .brand-header p {
            margin: .25rem 0 0;
            opacity: .8;
            font-size: .875rem;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .btn-primary {
            background: #6d28d9;
            border-color: #6d28d9;
        }

        .btn-primary:hover {
            background: #5b21b6;
            border-color: #5b21b6;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="card">
            <div class="brand-header">
                <i class="bi bi-bar-chart-steps fs-1 mb-2 d-block"></i>
                <h4>Vendor Comparison</h4>
                <p>Harent Purchasing Portal</p>
            </div>
            <div class="card-body p-4">

                @if (session('error'))
                    <div class="alert alert-danger py-2">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger py-2">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror" placeholder="your@email.com"
                            required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror" placeholder="••••••••"
                            required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>

                <hr class="my-3">
                <div class="text-muted small">
                    <strong>Demo accounts</strong> (password: <code>harent123</code>)<br>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="staff@harent.com" title="Purchasing Staff">Staff</a>
                        <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="procurement@harent.com" title="Procurement Staff">Procurement</a>
                        <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="supervisor@harent.com" title="Purchasing Supervisor">Supervisor</a>
                        <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="manager@harent.com" title="Purchasing Manager">Manager</a>
                        <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="viewer@harent.com" title="Viewer">Viewer</a>
                        {{-- <a href="#" class="badge bg-light text-dark border text-decoration-none demo-login"
                            data-email="admin@harent.com" title="Admin">Admin</a> --}}
                    </div>
                    <div class="text-muted mt-1" style="font-size:.7rem">Click a role to auto-fill email</div>
                </div>
                <script>
                    document.querySelectorAll('.demo-login').forEach(function(el) {
                        el.addEventListener('click', function(e) {
                            e.preventDefault();
                            document.querySelector('input[name=email]').value = this.dataset.email;
                            document.querySelector('input[name=password]').value = 'harent123';
                        });
                    });
                </script>
            </div>
        </div>
    </div>
</body>

</html>
