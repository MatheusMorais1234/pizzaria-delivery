<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Pizzaria Bella Napoli') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pizza: { red: '#DC2626', orange: '#EA580C' }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .cart-bounce { animation: bounce 0.4s ease; }
        @keyframes bounce {
            0%,100% { transform: scale(1); }
            50%      { transform: scale(1.4); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
