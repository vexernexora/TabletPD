<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Center - Police System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .games-container {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .games-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .games-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .games-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .games-header p {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .stats-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
        }
        
        .stat-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon svg {
            width: 20px; height: 20px;
            fill: white;
        }
        
        .stat-text {
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .game-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }
        
        .game-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        
        .game-card-header {
            padding: 30px;
            background: linear-gradient(135deg, var(--card-color) 0%, var(--card-color-dark) 100%);
            color: white;
            position: relative;
            overflow: hidden;
            height: 140px;
            display: flex;
            align-items: center;
        }
        
        .game-card-header::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 120px; height: 120px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .game-content {
            display: flex;
            align-items: center;
            gap: 25px;
            position: relative;
            z-index: 1;
            width: 100%;
        }
        
        .game-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .game-icon svg {
            width: 50px;
            height: 50px;
            fill: white;
        }
        
        .game-info h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .game-info p {
            font-size: 15px;
            opacity: 0.9;
        }
        
        .game-card-body {
            padding: 30px;
        }
        
        .game-description {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .game-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .difficulty {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .difficulty.easy { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .difficulty.medium { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .difficulty.hard { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
        .players {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }
        
        .play-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--button-color) 0%, var(--button-color-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .play-button::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .play-button:hover::before {
            left: 100%;
        }
        
        .play-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .card-dino { --card-color: #10b981; --card-color-dark: #059669; --button-color: #10b981; --button-color-dark: #059669; }
        .card-space { --card-color: #06b6d4; --card-color-dark: #0891b2; --button-color: #06b6d4; --button-color-dark: #0891b2; }
        .card-minesweeper { --card-color: #ef4444; --card-color-dark: #dc2626; --button-color: #ef4444; --button-color-dark: #dc2626; }
        .card-battle { --card-color: #8b5cf6; --card-color-dark: #7c3aed; --button-color: #8b5cf6; --button-color-dark: #7c3aed; }
        
        .game-iframe {
            display: none;
            width: 100%;
            height: 80vh;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-top: 30px;
        }
        
        .back-button {
            display: none;
            padding: 12px 24px;
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
        }
        
        @media (max-width: 768px) {
            .games-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .game-card-header {
                padding: 20px;
                height: 120px;
            }
            
            .game-icon {
                width: 60px;
                height: 60px;
            }
            
            .game-icon svg {
                width: 35px;
                height: 35px;
            }
            
            .game-info h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="games-container">
        <div class="games-header">
            <h1>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="white">
                    <path d="M21,6H18V5A2,2 0 0,0 16,3H8A2,2 0 0,0 6,5V6H3C2.45,6 2,6.45 2,7V10C2,10.55 2.45,11 3,11H4V19A2,2 0 0,0 6,21H18A2,2 0 0,0 20,19V11H21C21.55,11 22,10.55 22,10V7C22,6.45 21.55,6 21,6M8,5H16V6H8V5M18,19H6V11H8V15H16V11H18V19Z"/>
                </svg>
                Game Center
            </h1>
            <p>Odpocznij od pracy i zagraj w jedną z naszych gier - idealny sposób na relaks podczas przerwy!</p>
        </div>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                </div>
                <span class="stat-text">4 dostępne gry</span>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <span class="stat-text">Tryb pojedynczy i multiplayer</span>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M19,5V19H5V5H19Z"/>
                    </svg>
                </div>
                <span class="stat-text">Gry w tym samym oknie</span>
            </div>
        </div>
        
        <button class="back-button" id="backButton" onclick="showGameList()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 8px;">
                <path d="M19 7v4H5.83l3.58-3.59L8 6l-6 6 6 6 1.41-1.41L5.83 13H21V7z"/>
            </svg>
            Wróć do listy gier
        </button>
        
        <div class="games-grid" id="gamesList">
            <!-- Chrome Dino -->
            <div class="game-card card-dino" onclick="openGame('dino')">
                <div class="game-card-header">
                    <div class="game-content">
                        <div class="game-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M12,2A1,1 0 0,1 13,3V8H16A2,2 0 0,1 18,10V11H19A1,1 0 0,1 20,12A1,1 0 0,1 19,13H18V16A2,2 0 0,1 16,18H13V21A1,1 0 0,1 12,22A1,1 0 0,1 11,21V18H8A2,2 0 0,1 6,16V13H5A1,1 0 0,1 4,12A1,1 0 0,1 5,11H6V10A2,2 0 0,1 8,8H11V3A1,1 0 0,1 12,2M8,10V16H16V10H8M10,12H14V14H10V12Z"/>
                            </svg>
                        </div>
                        <div class="game-info">
                            <h2>Chrome Dino</h2>
                            <p>Klasyczna gra z przeglądarki Chrome</p>
                        </div>
                    </div>
                </div>
                <div class="game-card-body">
                    <div class="game-description">
                        Skacz przez kaktusy jako dinozaur w tej kultowej grze znanej z przeglądarki Chrome. Prosta kontrola, nieskończona rozrywka!
                    </div>
                    <div class="game-meta">
                        <span class="difficulty easy">Łatwy</span>
                        <span class="players">1 Gracz</span>
                    </div>
                    <button class="play-button">Zagraj Teraz</button>
                </div>
            </div>
            
            <!-- Space Shooter -->
            <div class="game-card card-space" onclick="openGame('space')">
                <div class="game-card-header">
                    <div class="game-content">
                        <div class="game-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M12,2L14.09,8.26L22,9L16,14.74L17.18,22.5L12,19.77L6.82,22.5L8,14.74L2,9L9.91,8.26L12,2M12,15.4L16.18,17.5L15.45,12.7L19.17,9.24L14.31,8.63L12,4.1L9.69,8.63L4.83,9.24L8.55,12.7L7.82,17.5L12,15.4Z"/>
                            </svg>
                        </div>
                        <div class="game-info">
                            <h2>Space Shooter</h2>
                            <p>Strzelaj do asteroid w kosmosie</p>
                        </div>
                    </div>
                </div>
                <div class="game-card-body">
                    <div class="game-description">
                        Pilotuj statek kosmiczny i niszcz asteroidy. Unikaj kolizji i zdobywaj punkty w tej dynamicznej strzelance!
                    </div>
                    <div class="game-meta">
                        <span class="difficulty medium">Średni</span>
                        <span class="players">1 Gracz</span>
                    </div>
                    <button class="play-button">Zagraj Teraz</button>
                </div>
            </div>
            
            <!-- Minesweeper -->
            <div class="game-card card-minesweeper" onclick="openGame('minesweeper')">
                <div class="game-card-header">
                    <div class="game-content">
                        <div class="game-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M23,12L20.56,9.22L20.9,5.54L17.29,4.72L15.4,1.54L12,3L8.6,1.54L6.71,4.72L3.1,5.53L3.44,9.21L1,12L3.44,14.78L3.1,18.47L6.71,19.29L8.6,22.47L12,21L15.4,22.46L17.29,19.28L20.9,18.46L20.56,14.78L23,12M13,17H11V15H13V17M13,13H11V7H13V13Z"/>
                            </svg>
                        </div>
                        <div class="game-info">
                            <h2>Saper</h2>
                            <p>Klasyczna gra w Sapera</p>
                        </div>
                    </div>
                </div>
                <div class="game-card-body">
                    <div class="game-description">
                        Odkrywaj pola i unikaj min. Używaj logiki i strategii, aby wyczyścić całe pole minowe!
                    </div>
                    <div class="game-meta">
                        <span class="difficulty hard">Trudny</span>
                        <span class="players">1 Gracz</span>
                    </div>
                    <button class="play-button">Zagraj Teraz</button>
                </div>
            </div>
            
            <!-- Battle Arena -->
            <div class="game-card card-battle" onclick="openGame('battle')">
                <div class="game-card-header">
                    <div class="game-content">
                        <div class="game-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M9,2V7.5A2.5,2.5 0 0,0 11.5,10A2.5,2.5 0 0,0 14,7.5V2H16V7.5A4.5,4.5 0 0,1 11.5,12A4.5,4.5 0 0,1 7,7.5V2M9,13V22H11V16H12V22H14V13.5A2.5,2.5 0 0,0 16.5,11A2.5,2.5 0 0,0 14,8.5V13A0.5,0.5 0 0,1 13.5,13.5A0.5,0.5 0 0,1 13,13V8.5A2.5,2.5 0 0,0 10.5,11A2.5,2.5 0 0,0 13,13.5V13"/>
                            </svg>
                        </div>
                        <div class="game-info">
                            <h2>Battle Arena</h2>
                            <p>Multiplayer arena battle</p>
                        </div>
                    </div>
                </div>
                <div class="game-card-body">
                    <div class="game-description">
                        Walcz z przyjaciółmi na jednej klawiaturze! Dynamiczna gra dla dwóch graczy z prostą mechaniką i szybką rozgrywką.
                    </div>
                    <div class="game-meta">
                        <span class="difficulty easy">Łatwy</span>
                        <span class="players">2 Graczy</span>
                    </div>
                    <button class="play-button">Zagraj Teraz</button>
                </div>
            </div>
        </div>
        
        <iframe id="gameFrame" class="game-iframe" src=""></iframe>
    </div>
    
    <script>
        function openGame(gameName) {
            const gameFrame = document.getElementById('gameFrame');
            const gamesList = document.getElementById('gamesList');
            const backButton = document.getElementById('backButton');
            
            gameFrame.src = `gry/${gameName}.php`;
            gamesList.style.display = 'none';
            gameFrame.style.display = 'block';
            backButton.style.display = 'block';
        }
        
        function showGameList() {
            const gameFrame = document.getElementById('gameFrame');
            const gamesList = document.getElementById('gamesList');
            const backButton = document.getElementById('backButton');
            
            gameFrame.src = '';
            gamesList.style.display = 'grid';
            gameFrame.style.display = 'none';
            backButton.style.display = 'none';
        }
        
        console.log('Game Center loaded - 4 games available with iframe support!');
    </script>
</body>
</html>