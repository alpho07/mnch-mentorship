<div class="fi-simple-page" style="padding: 0 !important; margin: 0 !important; max-width: none !important;">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .container {
                background: #1f2937;
            }
            
            form {
                background: #111827;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3),
                            0 8px 16px rgba(0, 0, 0, 0.4);
            }
            
            .form-title {
                color: #f9fafb;
            }
            
            .form-subtitle {
                color: #9ca3af;
            }
            
            form input {
                background: #1f2937;
                border-color: #374151;
                color: #f9fafb;
            }
            
            form input::placeholder {
                color: #6b7280;
            }
            
            form input:focus {
                background: #1f2937;
                border-color: #10b981;
            }
            
            .remember-me {
                color: #d1d5db;
            }
            
            .footer-text {
                color: #9ca3af;
            }
            
            /* Make Filament labels visible in dark mode */
            form label {
                color: #e5e7eb !important;
            }
            
            /* Filament form field text */
            .fi-fo-field-wrp-label {
                color: #e5e7eb !important;
            }
        }
        
        /* Light mode label colors */
        form label {
            color: #374151;
            font-weight: 500;
        }
        
        .fi-fo-field-wrp-label {
            color: #374151;
        }
        
        .fi-simple-page {
            min-height: 100vh !important;
            height: 100vh !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }
        
        .fi-simple-main {
            padding: 0 !important;
            max-width: none !important;
            width: 100% !important;
        }
        
        .flex {
            display: flex;
            align-items: center;
        }
        
        .container {
            padding: 0 40px;
            min-height: 100vh;
            justify-content: center;
            background: #f0f2f5;
        }
        
        .mnch-page {
            justify-content: space-between;
            max-width: 1100px;
            width: 100%;
            gap: 80px;
        }
        
        .mnch-page .text {
            flex: 1;
            margin-bottom: 80px;
        }
        
        .mnch-page h1 {
            color: #10b981;
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .mnch-page .tagline {
            color: #1f2937;
            font-size: 1.75rem;
            line-height: 1.4;
            margin-top: 16px;
        }
        
        form {
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1),
                        0 8px 16px rgba(0, 0, 0, 0.1);
            max-width: 420px;
            width: 100%;
        }
        
        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .form-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        
        form input {
            height: 52px;
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 1rem;
            padding: 0 16px;
            transition: all 0.2s ease;
        }
        
        /* Add spacing after Filament form */
        .fi-form {
            margin-bottom: 20px;
        }
        
        form input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        ::placeholder {
            color: #9ca3af;
            font-size: 0.95rem;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            font-size: 0.875rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
        }
        
        .forgot-link {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: #059669;
            text-decoration: underline;
        }
        
        .login-btn {
            border: none;
            outline: none;
            cursor: pointer;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 14px 0;
            border-radius: 8px;
            color: #fff;
            font-size: 1.125rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        hr {
            border: none;
            height: 1px;
            background-color: #e5e7eb;
            margin: 20px 0;
        }
        
        .register-section {
            text-align: center;
            padding-top: 8px;
        }
        
        .register-btn {
            display: inline-block;
            padding: 14px 32px;
            background: #10b981;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .register-btn:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .footer-text {
            text-align: center;
            color: #6b7280;
            font-size: 0.813rem;
            margin-top: 24px;
            line-height: 1.5;
        }
        
        .footer-text a {
            color: #10b981;
            text-decoration: none;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .mnch-page {
                flex-direction: column;
                text-align: center;
                gap: 40px;
            }
            
            .mnch-page .text {
                margin-bottom: 20px;
            }
            
            form {
                margin: 0 auto;
            }
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 0 20px;
            }
            
            .mnch-page h1 {
                font-size: 3rem;
            }
            
            .mnch-page .tagline {
                font-size: 1.35rem;
            }
            
            form {
                padding: 20px;
            }
        }
    </style>

    <div class="container flex">
        <div class="mnch-page flex">
            <!-- LEFT SIDE - Branding Text -->
            <div class="text">
                <h1>MNCH Platform</h1>
                <p class="tagline">
                    Transforming maternal and child health outcomes through evidence-based mentorship and collaborative learning.
                </p>
            </div>
            
            <!-- RIGHT SIDE - Login Form Card -->
            <form wire:submit="register">
                <h2 class="form-title">Create account</h2>
                <p class="form-subtitle">Join thousands of healthcare professionals</p>
                
                {{ $this->form }}
                
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="login-btn">
                    Create account
                </button>
                
                <hr>
                
                <div class="register-section">
                    <a href="{{ route('filament.admin.auth.login') }}" class="register-btn">
                        Already have an account? Sign in
                    </a>
                </div>
                
                <p class="footer-text">
                    By creating an account, you agree to our 
                    <a href="#">Terms</a> and 
                    <a href="#">Privacy Policy</a>
                </p>
            </form>
        </div>
    </div>
</div>