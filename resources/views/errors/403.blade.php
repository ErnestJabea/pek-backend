<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès Restreint | PEK Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }
        body {
            background-color: #0b0f17;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(0, 154, 77, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
        }
        .card {
            background: rgba(18, 24, 38, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            border-radius: 1.25rem;
            max-width: 32rem;
            width: 100%;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(0, 154, 77, 0.15);
            text-align: center;
        }
        .header-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        .header-logo img {
            height: 2.5rem;
            width: 2.5rem;
            border-radius: 0.5rem;
            object-contain: contain;
        }
        .header-logo span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #009a4d;
            letter-spacing: -0.025em;
        }
        .icon-container {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #f59e0b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem auto;
        }
        .icon-container svg {
            width: 2rem;
            height: 2rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }
        p.description {
            font-size: 0.925rem;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }
        .context-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.75rem;
            text-align: left;
            font-size: 0.85rem;
        }
        .context-row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }
        .context-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .context-label {
            color: #64748b;
            font-weight: 500;
        }
        .context-val {
            color: #cbd5e1;
            font-weight: 600;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #009a4d;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.65rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px 0 rgba(0, 154, 77, 0.39);
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #008743;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px 0 rgba(0, 154, 77, 0.45);
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 0.65rem;
            text-decoration: none;
            transition: color 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-secondary:hover {
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header-logo">
            <img src="/logo.png" alt="PEK" onerror="if (this.src.indexOf('logo-kori.png') === -1) this.src='/logo-kori.png';">
            <span>PEK Admin</span>
        </div>

        <div class="icon-container">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>

        <h1>Espace ou Action Restreint(e)</h1>
        
        <p class="description">
            Votre profil actuel ne dispose pas des privilèges nécessaires pour accéder à cette rubrique ou exécuter cette opération. Cette restriction dépend des <strong>rubriques</strong> allouées à votre rôle ou des <strong>actions autorisées</strong> pour votre département.
        </p>

        @if(auth()->check())
            @php
                $user = auth()->user();
                $roles = $user->roles->pluck('name')->map(fn($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') ?: 'Collaborateur';
                $deptName = $user->adminDepartment?->name ?: 'Non assigné';
            @endphp
            <div class="context-box">
                <div class="context-row">
                    <span class="context-label">Collaborateur</span>
                    <span class="context-val">{{ $user->name ?? $user->email }}</span>
                </div>
                <div class="context-row">
                    <span class="context-label">Rôle (Rubriques)</span>
                    <span class="context-val">{{ $roles }}</span>
                </div>
                <div class="context-row">
                    <span class="context-label">Département (Actions)</span>
                    <span class="context-val">{{ $deptName }}</span>
                </div>
            </div>
        @endif

        <div class="actions">
            <a href="{{ url('/admin') }}" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Revenir au tableau de bord
            </a>

            @if(auth()->check())
                <form action="{{ route('filament.admin.auth.logout') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn-secondary">
                        Se connecter avec un autre compte
                    </button>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
