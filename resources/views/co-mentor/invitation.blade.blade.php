<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Co-Mentor Invitation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-8">
                    <div class="mx-auto h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Co-Mentor Invitation</h2>
                    <p class="mt-2 text-sm text-gray-600">You've been invited to join as a co-mentor</p>
                </div>

                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Training Details</h3>
                    <p class="text-sm text-gray-700"><strong>Name:</strong> {{ $training->name }}</p>
                    @if($training->description)
                        <p class="text-sm text-gray-700 mt-1"><strong>Description:</strong> {{ $training->description }}</p>
                    @endif
                    <p class="text-sm text-gray-600 mt-2">
                        <strong>Invited by:</strong> {{ $inviter->name ?? 'Unknown' }}
                    </p>
                </div>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-800">{{ session('success') }}</p>
                    </div>
                @endif

                @if(session('info'))
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">{{ session('info') }}</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-800">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @auth
                    @if(Auth::id() === $invitation->user_id)
                        <form method="POST" action="{{ route('co-mentor.accept.process', $token) }}" class="space-y-4">
                            @csrf
                            <div class="flex gap-3">
                                <button type="submit" name="action" value="accept" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-150">
                                    Accept Invitation
                                </button>
                                <button type="submit" name="action" value="decline" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-150">
                                    Decline
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
                            <p class="text-sm text-yellow-800">This invitation is not for your account.</p>
                        </div>
                    @endif
                @else
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
                        <p class="text-sm text-yellow-800 mb-3">Please log in to respond to this invitation</p>
                        <a href="{{ route('filament.admin.auth.login') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-150">
                            Log In
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</body>
</html>