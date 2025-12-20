// One Piece Odyssey - Complete Game
// Game Data
const STRAW_HAT_CREW = [
    { id: 'luffy', name: 'Monkey D. Luffy', role: 'Captain', hp: 150, attack: 25, speed: 20, 
      defaultShirt: 'red_vest', defaultPants: 'blue_shorts', defaultHat: 'straw_hat',
      shirtColor: '#ff0000', pantsColor: '#0066cc', hairColor: '#000000', skinColor: '#ffcc99' },
    { id: 'zoro', name: 'Roronoa Zoro', role: 'Swordsman', hp: 120, attack: 30, speed: 15,
      defaultShirt: 'teal_shirt', defaultPants: 'blue_pants', defaultHat: 'green_bandana',
      shirtColor: '#4ecdc4', pantsColor: '#0066cc', hairColor: '#00ff00', skinColor: '#d4a574' },
    { id: 'nami', name: 'Nami', role: 'Navigator', hp: 80, attack: 15, speed: 25,
      defaultShirt: 'orange_bra', defaultPants: 'blue_jeans', defaultHat: null,
      shirtColor: '#ffa500', pantsColor: '#0066cc', hairColor: '#ff8c00', skinColor: '#ffcc99' },
    { id: 'usopp', name: 'Usopp', role: 'Sniper', hp: 70, attack: 20, speed: 18,
      defaultShirt: 'green_shirt', defaultPants: 'brown_shorts', defaultHat: 'yellow_bandana',
      shirtColor: '#95e1d3', pantsColor: '#654321', hairColor: '#000000', skinColor: '#8b4513' },
    { id: 'sanji', name: 'Sanji', role: 'Chef', hp: 110, attack: 28, speed: 22,
      defaultShirt: 'black_suit', defaultPants: 'black_pants', defaultHat: null,
      shirtColor: '#000000', pantsColor: '#000000', hairColor: '#ffd700', skinColor: '#ffcc99' },
    { id: 'chopper', name: 'Tony Tony Chopper', role: 'Doctor', hp: 90, attack: 18, speed: 16,
      defaultShirt: 'yellow_shirt', defaultPants: 'blue_shorts', defaultHat: 'red_hat',
      shirtColor: '#f9ca24', pantsColor: '#0066cc', hairColor: '#ffb6c1', skinColor: '#ffb6c1' },
    { id: 'robin', name: 'Nico Robin', role: 'Archaeologist', hp: 85, attack: 22, speed: 14,
      defaultShirt: 'purple_shirt', defaultPants: 'purple_pants', defaultHat: null,
      shirtColor: '#a29bfe', pantsColor: '#4b0082', hairColor: '#000000', skinColor: '#ffcc99' },
    { id: 'franky', name: 'Franky', role: 'Shipwright', hp: 140, attack: 26, speed: 12,
      defaultShirt: 'blue_shirt', defaultPants: 'red_shorts', defaultHat: null,
      shirtColor: '#00b894', pantsColor: '#ff6b6b', hairColor: '#00ffff', skinColor: '#00b894' },
    { id: 'brook', name: 'Brook', role: 'Musician', hp: 100, attack: 24, speed: 19,
      defaultShirt: 'purple_shirt', defaultPants: 'black_pants', defaultHat: 'top_hat',
      shirtColor: '#6c5ce7', pantsColor: '#000000', hairColor: '#ffff00', skinColor: '#ffffff' },
    { id: 'jinbe', name: 'Jinbe', role: 'Helmsman', hp: 130, attack: 27, speed: 13,
      defaultShirt: 'blue_shirt', defaultPants: 'blue_pants', defaultHat: null,
      shirtColor: '#0984e3', pantsColor: '#0066cc', hairColor: '#000000', skinColor: '#0984e3' }
];

const FACES = [
    { id: 'default', name: 'Default', emoji: '😊' },
    { id: 'happy', name: 'Happy', emoji: '😄' },
    { id: 'determined', name: 'Determined', emoji: '😤' },
    { id: 'serious', name: 'Serious', emoji: '😠' }
];

const CLOTHES = [
    { id: 'red_vest', name: 'Red Vest (Luffy)', type: 'shirt', color: '#ff0000', stat: 'hp', value: 10 },
    { id: 'teal_shirt', name: 'Teal Shirt (Zoro)', type: 'shirt', color: '#4ecdc4', stat: 'hp', value: 12 },
    { id: 'orange_bra', name: 'Orange Top (Nami)', type: 'shirt', color: '#ffa500', stat: 'speed', value: 8 },
    { id: 'green_shirt', name: 'Green Shirt (Usopp)', type: 'shirt', color: '#95e1d3', stat: 'hp', value: 10 },
    { id: 'black_suit', name: 'Black Suit (Sanji)', type: 'shirt', color: '#000000', stat: 'attack', value: 8 },
    { id: 'yellow_shirt', name: 'Yellow Shirt (Chopper)', type: 'shirt', color: '#f9ca24', stat: 'hp', value: 10 },
    { id: 'purple_shirt', name: 'Purple Shirt (Robin)', type: 'shirt', color: '#a29bfe', stat: 'hp', value: 12 },
    { id: 'blue_shirt', name: 'Blue Shirt', type: 'shirt', color: '#00b894', stat: 'hp', value: 10 },
    { id: 'blue_shorts', name: 'Blue Shorts (Luffy)', type: 'pants', color: '#0066cc', stat: 'speed', value: 8 },
    { id: 'blue_pants', name: 'Blue Pants (Zoro)', type: 'pants', color: '#0066cc', stat: 'speed', value: 5 },
    { id: 'blue_jeans', name: 'Blue Jeans (Nami)', type: 'pants', color: '#0066cc', stat: 'speed', value: 6 },
    { id: 'brown_shorts', name: 'Brown Shorts (Usopp)', type: 'pants', color: '#654321', stat: 'hp', value: 8 },
    { id: 'black_pants', name: 'Black Pants (Sanji)', type: 'pants', color: '#000000', stat: 'attack', value: 5 },
    { id: 'purple_pants', name: 'Purple Pants (Robin)', type: 'pants', color: '#4b0082', stat: 'hp', value: 10 },
    { id: 'red_shorts', name: 'Red Shorts (Franky)', type: 'pants', color: '#ff6b6b', stat: 'speed', value: 7 },
    { id: 'straw_hat', name: 'Straw Hat (Luffy)', type: 'hat', color: '#ffd700', stat: 'speed', value: 10 },
    { id: 'green_bandana', name: 'Green Bandana (Zoro)', type: 'hat', color: '#00ff00', stat: 'attack', value: 8 },
    { id: 'yellow_bandana', name: 'Yellow Bandana (Usopp)', type: 'hat', color: '#ffd700', stat: 'speed', value: 5 },
    { id: 'red_hat', name: 'Red Hat (Chopper)', type: 'hat', color: '#ff0000', stat: 'hp', value: 15 },
    { id: 'top_hat', name: 'Top Hat (Brook)', type: 'hat', color: '#000000', stat: 'attack', value: 10 }
];

const MOVES = {
    luffy: [
        { name: 'Gum-Gum Pistol', damage: 30, cooldown: 2, key: '1', description: 'A powerful punch attack', type: 'physical' },
        { name: 'Gum-Gum Bazooka', damage: 50, cooldown: 5, key: '2', description: 'Devastating double punch', type: 'physical' },
        { name: 'Gum-Gum Gatling', damage: 40, cooldown: 4, key: '3', description: 'Rapid fire punches', type: 'physical' },
        { name: 'Gear Second', damage: 60, cooldown: 8, key: '4', description: 'Speed boost and power increase', type: 'buff' },
        { name: 'Gum-Gum Elephant Gun', damage: 70, cooldown: 10, key: '5', description: 'Massive giant punch', type: 'physical' }
    ],
    zoro: [
        { name: 'Oni Giri', damage: 35, cooldown: 3, key: '1', description: 'Three sword style attack', type: 'physical' },
        { name: 'Santoryu Ogi', damage: 55, cooldown: 6, key: '2', description: 'Ultimate three sword technique', type: 'physical' },
        { name: 'Tatsumaki', damage: 45, cooldown: 4, key: '3', description: 'Tornado slash attack', type: 'physical' },
        { name: 'Asura', damage: 80, cooldown: 12, key: '4', description: 'Nine sword style ultimate', type: 'physical' },
        { name: 'Dai Ittoryu', damage: 50, cooldown: 5, key: '5', description: 'Single sword powerful slash', type: 'physical' }
    ],
    nami: [
        { name: 'Thunderbolt Tempo', damage: 25, cooldown: 4, key: '1', description: 'Lightning attack', type: 'magic' },
        { name: 'Thunder Lance Tempo', damage: 35, cooldown: 5, key: '2', description: 'Powerful lightning lance', type: 'magic' },
        { name: 'Mirage Tempo', damage: 0, cooldown: 6, key: '3', description: 'Create illusions to dodge', type: 'buff' },
        { name: 'Cyclone Tempo', damage: 40, cooldown: 6, key: '4', description: 'Wind and lightning combo', type: 'magic' },
        { name: 'Zeus Thunder', damage: 60, cooldown: 10, key: '5', description: 'Ultimate lightning strike', type: 'magic' }
    ],
    sanji: [
        { name: 'Diable Jambe', damage: 40, cooldown: 4, key: '1', description: 'Fire kick attack', type: 'physical' },
        { name: 'Hell Memories', damage: 55, cooldown: 6, key: '2', description: 'Powerful fire kick combo', type: 'physical' },
        { name: 'Sky Walk', damage: 0, cooldown: 5, key: '3', description: 'Air mobility boost', type: 'buff' },
        { name: 'Mouton Shot', damage: 45, cooldown: 5, key: '4', description: 'Spinning fire kick', type: 'physical' },
        { name: 'Ifrit Jambe', damage: 70, cooldown: 10, key: '5', description: 'Ultimate fire kick', type: 'physical' }
    ],
    chopper: [
        { name: 'Heavy Point', damage: 25, cooldown: 3, key: '1', description: 'Transform and attack', type: 'physical' },
        { name: 'Guard Point', damage: 0, cooldown: 4, key: '2', description: 'Defense boost', type: 'buff' },
        { name: 'Monster Point', damage: 60, cooldown: 12, key: '3', description: 'Transform into monster', type: 'physical' },
        { name: 'Horn Point', damage: 35, cooldown: 4, key: '4', description: 'Horn charge attack', type: 'physical' },
        { name: 'Kung Fu Point', damage: 40, cooldown: 5, key: '5', description: 'Martial arts combo', type: 'physical' }
    ],
    robin: [
        { name: 'Clutch', damage: 30, cooldown: 3, key: '1', description: 'Multiple arm attack', type: 'physical' },
        { name: 'Gigantesco Mano', damage: 45, cooldown: 5, key: '2', description: 'Giant hand attack', type: 'physical' },
        { name: 'Mil Fleur', damage: 35, cooldown: 4, key: '3', description: 'Thousand hands attack', type: 'physical' },
        { name: 'Dos Fleur', damage: 40, cooldown: 5, key: '4', description: 'Double hand combo', type: 'physical' },
        { name: 'Demonio Fleur', damage: 70, cooldown: 12, key: '5', description: 'Demon form ultimate', type: 'physical' }
    ],
    franky: [
        { name: 'Coup de Vent', damage: 35, cooldown: 4, key: '1', description: 'Air cannon', type: 'physical' },
        { name: 'Franky Radical Beam', damage: 50, cooldown: 6, key: '2', description: 'Energy beam attack', type: 'magic' },
        { name: 'Strong Right', damage: 40, cooldown: 5, key: '3', description: 'Powerful punch', type: 'physical' },
        { name: 'Franky Shogun', damage: 0, cooldown: 8, key: '4', description: 'Transform into mech', type: 'buff' },
        { name: 'Franky Shogun Cannon', damage: 75, cooldown: 12, key: '5', description: 'Ultimate mech cannon', type: 'magic' }
    ],
    brook: [
        { name: 'Soul Solid', damage: 30, cooldown: 3, key: '1', description: 'Ice sword attack', type: 'physical' },
        { name: 'Vivre Card', damage: 0, cooldown: 5, key: '2', description: 'Heal party member', type: 'heal' },
        { name: 'Blizzard Slash', damage: 45, cooldown: 5, key: '3', description: 'Ice area attack', type: 'magic' },
        { name: 'Yomi Yomi no Mi', damage: 0, cooldown: 8, key: '4', description: 'Revive from death', type: 'buff' },
        { name: 'Soul King Concert', damage: 60, cooldown: 10, key: '5', description: 'Musical ultimate attack', type: 'magic' }
    ],
    jinbe: [
        { name: 'Fish-Man Karate', damage: 35, cooldown: 3, key: '1', description: 'Water-based attack', type: 'physical' },
        { name: 'Vagabond Drill', damage: 45, cooldown: 5, key: '2', description: 'Water drill attack', type: 'physical' },
        { name: 'Water Lasso', damage: 40, cooldown: 4, key: '3', description: 'Water whip attack', type: 'physical' },
        { name: 'Shark Skin', damage: 0, cooldown: 6, key: '4', description: 'Defense boost', type: 'buff' },
        { name: 'Ocean Current', damage: 70, cooldown: 12, key: '5', description: 'Massive water attack', type: 'magic' }
    ]
};

const TEACHERS = [
    { 
        name: 'Rayleigh', 
        emoji: '👴', 
        moves: [
            { name: 'Advanced Haki', cost: 5000, description: 'Master all forms of Haki', statBoost: { attack: 10, hp: 20 } },
            { name: 'Observation Haki', cost: 3000, description: 'See into the future', statBoost: { speed: 15 } }
        ] 
    },
    { 
        name: 'Mihawk', 
        emoji: '⚔️', 
        moves: [
            { name: 'Sword Mastery', cost: 4000, description: 'Become a master swordsman', statBoost: { attack: 20 } }
        ] 
    }
];

const ISLANDS = [
    { id: 1, name: 'East Blue', boss: 'Arlong', bossHP: 200, bossEmoji: '🦈', berryReward: 5000, bossColor: '#0066cc', bossHair: '#000000', bossType: 'fishman' },
    { id: 2, name: 'Alabasta', boss: 'Crocodile', bossHP: 300, bossEmoji: '🐊', berryReward: 10000, bossColor: '#8b4513', bossHair: '#000000', bossType: 'warlord' },
    { id: 3, name: 'Enies Lobby', boss: 'Rob Lucci', bossHP: 400, bossEmoji: '🐆', berryReward: 15000, bossColor: '#654321', bossHair: '#000000', bossType: 'cp9' },
    { id: 4, name: 'Marineford', boss: 'Akainu', bossHP: 500, bossEmoji: '🌋', berryReward: 20000, bossColor: '#ff0000', bossHair: '#000000', bossType: 'admiral' },
    { id: 5, name: 'Dressrosa', boss: 'Doflamingo', bossHP: 600, bossEmoji: '🕷️', berryReward: 25000, bossColor: '#ff00ff', bossHair: '#ffff00', bossType: 'warlord' },
    { id: 6, name: 'Whole Cake Island', boss: 'Big Mom', bossHP: 700, bossEmoji: '👑', berryReward: 30000, bossColor: '#ff69b4', bossHair: '#ff69b4', bossType: 'yonko' },
    { id: 7, name: 'Wano Country', boss: 'Kaido', bossHP: 1000, bossEmoji: '🐉', berryReward: 50000, bossColor: '#8b0000', bossHair: '#000000', bossType: 'yonko' }
];

const ENEMIES = [
    { name: 'Marine Soldier', hp: 50, berryReward: 200, attack: 8, color: '#0066cc', hairColor: '#000000' },
    { name: 'Marine Captain', hp: 80, berryReward: 400, attack: 12, color: '#004499', hairColor: '#8b4513' },
    { name: 'Pirate Thug', hp: 60, berryReward: 300, attack: 10, color: '#8b4513', hairColor: '#000000' },
    { name: 'Pirate Captain', hp: 100, berryReward: 500, attack: 15, color: '#654321', hairColor: '#8b4513' },
    { name: 'Bandit', hp: 40, berryReward: 150, attack: 6, color: '#696969', hairColor: '#000000' },
    { name: 'Sea Beast', hp: 70, berryReward: 350, attack: 11, color: '#228b22', hairColor: '#228b22' }
];

// Game State
let gameState = {
    selectedCharacter: null,
    currentIsland: 0,
    playerHP: 100,
    maxHP: 100,
    berries: 1000,
    learnedMoves: [],
    selectedFace: 'default',
    selectedClothes: { shirt: null, pants: null, hat: null },
    currentOutfit: { shirt: null, pants: null, hat: null },
    canvas: null,
    ctx: null,
    player: { x: 400, y: 300, width: 60, height: 80 },
    enemies: [],
    currentBoss: null,
    currentEnemy: null,
    isInCombat: false,
    isInCombatWithBoss: false,
    isPlayerTurn: true,
    moveCooldowns: {},
    enemiesDefeated: 0,
    totalEnemies: 0,
    enemiesRemaining: 0
};

// Initialize Game
function init() {
    simulateLoading();
    setupCharacterSelection();
}

function simulateLoading() {
    const loadingScreen = document.getElementById('loadingScreen');
    const progressBar = document.getElementById('loadingProgress');
    const loadingText = document.getElementById('loadingText');
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        progressBar.style.width = progress + '%';
        
        if (progress < 30) loadingText.textContent = 'Loading the Grand Line...';
        else if (progress < 60) loadingText.textContent = 'Preparing the Straw Hat Crew...';
        else if (progress < 90) loadingText.textContent = 'Setting sail for adventure...';
        else loadingText.textContent = 'Ready to set sail!';
        
        if (progress >= 100) {
            clearInterval(interval);
            setTimeout(() => {
                loadingScreen.classList.add('hidden');
                document.getElementById('characterSelectScreen').classList.remove('hidden');
            }, 500);
        }
    }, 200);
}

function setupCharacterSelection() {
    const grid = document.getElementById('characterGrid');
    STRAW_HAT_CREW.forEach(character => {
        const card = document.createElement('div');
        card.className = 'character-card';
        card.innerHTML = `
            <canvas class="character-preview-canvas" width="120" height="180"></canvas>
            <h3>${character.name}</h3>
            <p>${character.role}</p>
        `;
        card.addEventListener('click', () => selectCharacter(character, card));
        grid.appendChild(card);
        
        // Draw preview
        const canvas = card.querySelector('canvas');
        const ctx = canvas.getContext('2d');
        drawOnePieceCharacter(ctx, character, 60, 90, character.defaultShirt, character.defaultPants, character.defaultHat, character.shirtColor, character.pantsColor);
    });
}

function selectCharacter(character, cardElement) {
    gameState.selectedCharacter = character;
    document.querySelectorAll('.character-card').forEach(card => card.classList.remove('selected'));
    cardElement.classList.add('selected');
    
    document.getElementById('selectedCharName').textContent = character.name;
    document.getElementById('selectedCharDesc').textContent = `${character.role} of the Straw Hat Pirates`;
    document.getElementById('charStats').innerHTML = `
        <div class="stat-item">HP: ${character.hp}</div>
        <div class="stat-item">Attack: ${character.attack}</div>
        <div class="stat-item">Speed: ${character.speed}</div>
    `;
    
    // Update preview
    const previewCanvas = document.getElementById('characterPreview');
    const previewCtx = previewCanvas.getContext('2d');
    previewCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
    drawOnePieceCharacter(previewCtx, character, 100, 150, character.defaultShirt, character.defaultPants, character.defaultHat, character.shirtColor, character.pantsColor);
    
    document.getElementById('startGameBtn').disabled = false;
}

document.getElementById('startGameBtn').addEventListener('click', () => {
    showCustomization();
});

function showCustomization() {
    document.getElementById('characterSelectScreen').classList.add('hidden');
    document.getElementById('customizationScreen').classList.remove('hidden');
    
    setupCustomization();
}

function setupCustomization() {
    // Setup faces
    const faceGrid = document.getElementById('faceGrid');
    faceGrid.innerHTML = '';
    FACES.forEach(face => {
        const item = document.createElement('div');
        item.className = 'face-item';
        item.textContent = `${face.emoji} ${face.name}`;
        item.addEventListener('click', () => {
            document.querySelectorAll('.face-item').forEach(f => f.classList.remove('selected'));
            item.classList.add('selected');
            gameState.selectedFace = face.id;
            updateCharacterPreview();
        });
        if (face.id === gameState.selectedFace) item.classList.add('selected');
        faceGrid.appendChild(item);
    });
    
    // Setup clothes
    setupClothingGrid('shirtGrid', 'shirt');
    setupClothingGrid('pantsGrid', 'pants');
    setupClothingGrid('hatGrid', 'hat');
    
    updateCharacterPreview();
}

function setupClothingGrid(gridId, type) {
    const grid = document.getElementById(gridId);
    grid.innerHTML = '';
    const clothes = CLOTHES.filter(c => c.type === type);
    clothes.forEach(cloth => {
        const item = document.createElement('div');
        item.className = 'accessory-item';
        item.style.backgroundColor = cloth.color;
        item.textContent = cloth.name;
        item.addEventListener('click', () => {
            document.querySelectorAll(`#${gridId} .accessory-item`).forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            gameState.selectedClothes[type] = cloth.id;
            updateCharacterPreview();
        });
        grid.appendChild(item);
    });
}

function updateCharacterPreview() {
    const canvas = document.getElementById('charPreview');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    const character = gameState.selectedCharacter;
    const shirtId = gameState.selectedClothes.shirt || character.defaultShirt;
    const pantsId = gameState.selectedClothes.pants || character.defaultPants;
    const hatId = gameState.selectedClothes.hat || character.defaultHat;
    const shirtColor = CLOTHES.find(c => c.id === shirtId)?.color || character.shirtColor;
    const pantsColor = CLOTHES.find(c => c.id === pantsId)?.color || character.pantsColor;
    
    drawOnePieceCharacter(ctx, character, 100, 150, shirtId, pantsId, hatId, shirtColor, pantsColor);
}

document.getElementById('confirmCustomization').addEventListener('click', () => {
    gameState.currentOutfit = { ...gameState.selectedClothes };
    startGame();
});

function startGame() {
    document.getElementById('customizationScreen').classList.add('hidden');
    document.getElementById('gameScreen').classList.remove('hidden');
    
    gameState.playerHP = gameState.maxHP = gameState.selectedCharacter.hp;
    
    init2DGame();
    setupGameControls();
    loadIsland(ISLANDS[gameState.currentIsland]);
}

function init2DGame() {
    gameState.canvas = document.getElementById('gameCanvas');
    gameState.ctx = gameState.canvas.getContext('2d');
    gameState.canvas.width = window.innerWidth;
    gameState.canvas.height = window.innerHeight;
    
    window.addEventListener('resize', () => {
        gameState.canvas.width = window.innerWidth;
        gameState.canvas.height = window.innerHeight;
    });
    
    gameLoop();
}

function gameLoop() {
    gameState.ctx.clearRect(0, 0, gameState.canvas.width, gameState.canvas.height);
    
    // Draw background
    gameState.ctx.fillStyle = '#87ceeb';
    gameState.ctx.fillRect(0, 0, gameState.canvas.width, gameState.canvas.height);
    
    // Draw ground
    gameState.ctx.fillStyle = '#90ee90';
    gameState.ctx.fillRect(0, gameState.canvas.height - 100, gameState.canvas.width, 100);
    
    // Draw player
    if (gameState.selectedCharacter) {
        drawAnimeCharacter(gameState.ctx, gameState.selectedCharacter, 
            gameState.player.x, gameState.player.y, true);
    }
    
    // Draw enemies
    gameState.enemies.forEach(enemy => {
        drawEnemy(gameState.ctx, enemy);
    });
    
    // Draw boss
    if (gameState.currentBoss) {
        drawBoss(gameState.ctx, gameState.currentBoss);
    }
    
    updateHUD();
    requestAnimationFrame(gameLoop);
}

function drawAnimeCharacter(ctx, character, x, y, useCustomClothes = false) {
    let outfit = {};
    if (useCustomClothes) {
        if (gameState.currentOutfit && (gameState.currentOutfit.shirt || gameState.currentOutfit.pants || gameState.currentOutfit.hat)) {
            outfit = gameState.currentOutfit;
        } else {
            outfit = gameState.selectedClothes;
        }
    }
    
    let shirtId = outfit.shirt;
    if (!shirtId && useCustomClothes) {
        shirtId = character.defaultShirt;
    }
    let pantsId = outfit.pants;
    if (!pantsId && useCustomClothes) {
        pantsId = character.defaultPants;
    }
    let hatId = outfit.hat;
    if (!hatId && useCustomClothes) {
        hatId = character.defaultHat;
    }
    
    let shirtColor = shirtId ? CLOTHES.find(c => c.id === shirtId)?.color : character.shirtColor;
    let pantsColor = pantsId ? CLOTHES.find(c => c.id === pantsId)?.color : character.pantsColor;
    
    drawOnePieceCharacter(ctx, character, x, y, shirtId, pantsId, hatId, shirtColor, pantsColor);
}

function drawOnePieceCharacter(ctx, character, x, y, shirtId, pantsId, hatId, shirtColor, pantsColor) {
    ctx.save();
    ctx.translate(x, y);
    
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    
    // Feet (small circles)
    ctx.fillStyle = pantsColor;
    ctx.beginPath();
    ctx.arc(-12, 60, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(12, 60, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Lower legs (circles)
    ctx.beginPath();
    ctx.arc(-12, 50, 7, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(12, 50, 7, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Upper legs (circles)
    ctx.beginPath();
    ctx.arc(-12, 40, 9, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(12, 40, 9, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Body/Torso (large circle)
    ctx.fillStyle = shirtColor;
    
    if (character.id === 'nami' && (shirtId === 'orange_bra' || (!shirtId && character.defaultShirt === 'orange_bra'))) {
        drawNamiDetailedTop(ctx, character);
    } else if (shirtId === 'red_vest' || (!shirtId && character.id === 'luffy')) {
        drawLuffyVest(ctx, character);
    } else if (shirtId === 'black_suit' || (!shirtId && character.id === 'sanji')) {
        drawSanjiSuit(ctx, character);
    } else {
        ctx.beginPath();
        ctx.arc(0, 20, 20, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
    }
    
    // Upper arms (circles)
    ctx.fillStyle = character.id === 'nami' && (shirtId === 'orange_bra' || (!shirtId && character.defaultShirt === 'orange_bra')) 
        ? character.skinColor : shirtColor;
    
    ctx.beginPath();
    ctx.arc(-20, 8, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(20, 8, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Lower arms (circles)
    ctx.beginPath();
    ctx.arc(-20, 20, 6, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(20, 20, 6, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Hands (small circles)
    ctx.fillStyle = character.skinColor;
    ctx.beginPath();
    ctx.arc(-20, 28, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(20, 28, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head (LARGE circle - One Piece Odyssey chibi style)
    ctx.fillStyle = character.skinColor || '#ffcc99';
    ctx.beginPath();
    ctx.arc(0, -30, 28, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Hair (detailed manga style)
    drawDetailedMangaHair(ctx, character, 0, -30);
    
    // Face (detailed manga style)
    drawDetailedMangaFace(ctx, character, 0, -30);
    
    // Hat/Accessories
    if (hatId === 'straw_hat' || (!hatId && character.id === 'luffy')) {
        drawDetailedStrawHat(ctx, 0, -50);
    } else if (hatId === 'green_bandana' || (!hatId && character.id === 'zoro')) {
        drawDetailedBandana(ctx, 0, -40, '#00ff00');
    } else if (hatId === 'red_hat' || (!hatId && character.id === 'chopper')) {
        drawDetailedChopperHat(ctx, 0, -50);
    } else if (hatId === 'top_hat' || (!hatId && character.id === 'brook')) {
        drawDetailedTopHat(ctx, 0, -50);
    } else if (hatId === 'yellow_bandana' || (!hatId && character.id === 'usopp')) {
        drawDetailedBandana(ctx, 0, -40, '#ffd700');
    }
    
    // Character-specific details (One Piece Odyssey style)
    if (character.id === 'zoro') {
        drawZoroSwords(ctx);
    }
    if (character.id === 'luffy') {
        // Luffy's scar more visible
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(-14, -20);
        ctx.lineTo(-8, -14);
        ctx.moveTo(-14, -14);
        ctx.lineTo(-8, -20);
        ctx.stroke();
    }
    if (character.id === 'nami') {
        // Nami's tangerine tattoo (simplified)
        ctx.fillStyle = '#ffa500';
        ctx.beginPath();
        ctx.arc(15, 5, 3, 0, Math.PI * 2);
        ctx.fill();
    }
    if (character.id === 'sanji') {
        // Sanji's eyebrow more visible
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(-14, -38, 5, 0, Math.PI * 6);
        ctx.stroke();
    }
    if (character.id === 'franky') {
        // Franky's cyborg details
        ctx.fillStyle = '#c0c0c0';
        ctx.beginPath();
        ctx.arc(-15, 5, 4, 0, Math.PI * 2);
        ctx.arc(15, 5, 4, 0, Math.PI * 2);
        ctx.fill();
    }
    if (character.id === 'brook') {
        // Brook's cane
        ctx.strokeStyle = '#8b4513';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(25, 10);
        ctx.lineTo(25, -10);
        ctx.stroke();
        ctx.fillStyle = '#ffd700';
        ctx.beginPath();
        ctx.arc(25, -10, 3, 0, Math.PI * 2);
        ctx.fill();
    }
    
    ctx.restore();
}

function drawLuffyVest(ctx, character) {
    ctx.fillStyle = '#ff0000';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.arc(0, 20, 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = character.skinColor;
    ctx.beginPath();
    ctx.arc(0, 12, 14, 0, Math.PI * 2);
    ctx.fill();
}

function drawNamiDetailedTop(ctx, character) {
    ctx.fillStyle = '#ffa500';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.arc(0, 20, 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(-8, 8, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(8, 8, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawSanjiSuit(ctx, character) {
    ctx.fillStyle = '#000000';
    ctx.strokeStyle = '#333';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.arc(0, 20, 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawDetailedMangaHair(ctx, character, x, y) {
    ctx.fillStyle = character.hairColor || '#000000';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    
    if (character.id === 'luffy') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 24, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        const spikes = [
            { angle: -0.8, length: 12 }, { angle: -0.4, length: 14 },
            { angle: -0.1, length: 16 }, { angle: 0, length: 18 },
            { angle: 0.1, length: 16 }, { angle: 0.4, length: 14 },
            { angle: 0.8, length: 12 }
        ];
        spikes.forEach(spike => {
            const spikeX = x + Math.cos(spike.angle) * 20;
            const spikeY = y - 20 + Math.sin(spike.angle) * 8 - spike.length;
            ctx.beginPath();
            ctx.moveTo(spikeX - 3, y - 20);
            ctx.lineTo(spikeX, spikeY);
            ctx.lineTo(spikeX + 3, y - 20);
            ctx.fill();
            ctx.stroke();
        });
    } else if (character.id === 'zoro') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 22, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        for (let i = 0; i < 5; i++) {
            const spikeX = x - 12 + i * 6;
            ctx.beginPath();
            ctx.moveTo(spikeX - 2, y - 20);
            ctx.lineTo(spikeX, y - 38);
            ctx.lineTo(spikeX + 2, y - 20);
            ctx.fill();
            ctx.stroke();
        }
    } else if (character.id === 'nami') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 20, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(x + 20, y - 15, 12, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#ff0000';
        ctx.beginPath();
        ctx.arc(x + 20, y - 20, 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = character.hairColor;
    } else if (character.id === 'sanji') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 20, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.strokeStyle = '#ffd700';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(x + 18, y - 20, 10, 0, Math.PI * 8);
        ctx.stroke();
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
    } else if (character.id === 'usopp') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 26, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        for (let i = 0; i < 12; i++) {
            const angle = (i / 12) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(x + Math.cos(angle) * 20, y - 20 + Math.sin(angle) * 20, 4, 0, Math.PI * 2);
            ctx.fill();
        }
    } else if (character.id === 'chopper') {
        ctx.fillStyle = '#ffb6c1';
        ctx.beginPath();
        ctx.arc(x, y - 20, 22, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
    } else if (character.id === 'robin') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 20, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(x, y + 10, 22, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(x, y + 30, 20, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
    } else if (character.id === 'franky') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 28, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        for (let i = 0; i < 6; i++) {
            const angle = (i / 6) * Math.PI * 2;
            const spikeX = x + Math.cos(angle) * 24;
            const spikeY = y - 20 + Math.sin(angle) * 24;
            ctx.beginPath();
            ctx.moveTo(x + Math.cos(angle) * 20, y - 20 + Math.sin(angle) * 20);
            ctx.lineTo(spikeX, spikeY - 8);
            ctx.lineTo(x + Math.cos(angle) * 20, y - 20 + Math.sin(angle) * 20);
            ctx.fill();
            ctx.stroke();
        }
    } else if (character.id === 'brook') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 28, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
    } else if (character.id === 'jinbe') {
        ctx.beginPath();
        ctx.arc(x, y - 20, 18, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
    }
}

function drawDetailedMangaFace(ctx, character, x, y) {
    ctx.strokeStyle = '#000';
    ctx.fillStyle = '#000';
    ctx.lineWidth = 2;
    
    if (character.id === 'luffy') {
        // Luffy's face - EXACT manga style (round eyes, big smile, X-scar)
        // Round eyes (manga style)
        ctx.beginPath();
        ctx.arc(x - 8, y - 5, 6, 0, Math.PI * 2);
        ctx.arc(x + 8, y - 5, 6, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine (white circles - manga style)
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 6, y - 6, 2.5, 0, Math.PI * 2);
        ctx.arc(x + 10, y - 6, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // Big characteristic smile (manga style - wider)
        ctx.beginPath();
        ctx.arc(x, y + 12, 16, 0, Math.PI);
        ctx.stroke();
        ctx.lineWidth = 3;
        // Teeth (white - manga style)
        ctx.fillStyle = '#fff';
        ctx.fillRect(x - 6, y + 10, 3, 6);
        ctx.fillRect(x + 3, y + 10, 3, 6);
        ctx.fillStyle = '#000';
        // X-shaped scar under left eye (EXACT manga style - more prominent)
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(x - 16, y + 1);
        ctx.lineTo(x - 8, y + 9);
        ctx.moveTo(x - 16, y + 9);
        ctx.lineTo(x - 8, y + 1);
        ctx.stroke();
        ctx.lineWidth = 2;
    } else if (character.id === 'zoro') {
        // Zoro's face - EXACT manga style (narrow serious eyes)
        // Narrow serious eyes (manga style - more defined)
        ctx.fillRect(x - 11, y - 8, 8, 2.5);
        ctx.fillRect(x + 3, y - 8, 8, 2.5);
        // Eye shine (small white dots)
        ctx.fillStyle = '#fff';
        ctx.fillRect(x - 9, y - 7.5, 2, 1);
        ctx.fillRect(x + 5, y - 7.5, 2, 1);
        ctx.fillStyle = '#000';
        // Serious straight mouth (manga style)
        ctx.beginPath();
        ctx.moveTo(x - 7, y + 6);
        ctx.lineTo(x + 7, y + 6);
        ctx.stroke();
        ctx.lineWidth = 2.5;
        ctx.lineWidth = 2;
    } else if (character.id === 'nami') {
        // Nami's face - EXACT manga style (cute round eyes, small nose)
        // Round cute eyes (manga style - larger)
        ctx.beginPath();
        ctx.arc(x - 7, y - 5, 5, 0, Math.PI * 2);
        ctx.arc(x + 7, y - 5, 5, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine (manga style)
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 5, y - 6, 2.5, 0, Math.PI * 2);
        ctx.arc(x + 9, y - 6, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // Small nose dot (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 2, 1.5, 0, Math.PI * 2);
        ctx.fill();
        // Cute smile (manga style - wider)
        ctx.beginPath();
        ctx.arc(x, y + 10, 12, 0, Math.PI);
        ctx.stroke();
    } else if (character.id === 'usopp') {
        // Usopp's face - EXACT manga style (round eyes, LONG nose signature)
        // Round eyes (manga style)
        ctx.beginPath();
        ctx.arc(x - 6, y - 3, 4, 0, Math.PI * 2);
        ctx.arc(x + 6, y - 3, 4, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 4, y - 4, 2, 0, Math.PI * 2);
        ctx.arc(x + 8, y - 4, 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // LONG NOSE (Usopp's signature - EXACT manga style - even longer)
        ctx.beginPath();
        ctx.arc(x, y + 2, 3.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.ellipse(x, y + 12, 3, 20, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.lineWidth = 2.5;
        ctx.lineWidth = 2;
    } else if (character.id === 'sanji') {
        // Sanji's face - EXACT manga style (round eyes, curly eyebrow signature)
        // Round eyes (manga style)
        ctx.beginPath();
        ctx.arc(x - 10, y - 6, 4, 0, Math.PI * 2);
        ctx.arc(x + 10, y - 6, 4, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 8, y - 7, 2, 0, Math.PI * 2);
        ctx.arc(x + 12, y - 7, 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // CURLY EYEBROW (Sanji's signature - EXACT manga style - more prominent)
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(x - 16, y - 8, 6, 0, Math.PI * 8);
        ctx.stroke();
        ctx.lineWidth = 2;
        // Small nose
        ctx.beginPath();
        ctx.arc(x, y + 2, 1.5, 0, Math.PI * 2);
        ctx.fill();
        // Smile (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 10, 10, 0, Math.PI);
        ctx.stroke();
    } else if (character.id === 'chopper') {
        // Chopper's face - EXACT manga style (reindeer, pink nose signature)
        // Round eyes (manga style)
        ctx.beginPath();
        ctx.arc(x - 6, y - 3, 4, 0, Math.PI * 2);
        ctx.arc(x + 6, y - 3, 4, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 4, y - 4, 2, 0, Math.PI * 2);
        ctx.arc(x + 8, y - 4, 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // PINK NOSE (Chopper's signature - EXACT manga style - larger)
        ctx.fillStyle = '#ff69b4';
        ctx.beginPath();
        ctx.arc(x, y + 4, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = '#000';
        // Mouth (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 12, 7, 0, Math.PI);
        ctx.stroke();
    } else if (character.id === 'robin') {
        // Robin's face - EXACT manga style (elegant, mature features)
        // Elegant eyes (manga style - larger)
        ctx.beginPath();
        ctx.arc(x - 7, y - 5, 5, 0, Math.PI * 2);
        ctx.arc(x + 7, y - 5, 5, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 5, y - 6, 2.5, 0, Math.PI * 2);
        ctx.arc(x + 9, y - 6, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // Small nose (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 2, 1.5, 0, Math.PI * 2);
        ctx.fill();
        // Elegant smile (manga style - wider)
        ctx.beginPath();
        ctx.arc(x, y + 10, 11, 0, Math.PI);
        ctx.stroke();
    } else if (character.id === 'franky') {
        // Franky's face - EXACT manga style (square cyborg sunglasses)
        // Square cyborg eyes (manga style - larger)
        ctx.fillRect(x - 13, y - 8, 10, 6);
        ctx.fillRect(x + 3, y - 8, 10, 6);
        // Small nose
        ctx.beginPath();
        ctx.arc(x, y + 2, 1.5, 0, Math.PI * 2);
        ctx.fill();
        // Wide smile (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 10, 12, 0, Math.PI);
        ctx.stroke();
    } else if (character.id === 'brook') {
        // Brook's face - EXACT manga style (skeleton skull)
        // Skull eye sockets (manga style - larger)
        ctx.beginPath();
        ctx.arc(x - 6, y - 5, 7, 0, Math.PI * 2);
        ctx.arc(x + 6, y - 5, 7, 0, Math.PI * 2);
        ctx.fill();
        // Skull nose hole (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 2, 2.5, 0, Math.PI * 2);
        ctx.fill();
        // Skull smile (wide grin - manga style)
        ctx.beginPath();
        ctx.arc(x, y + 9, 14, 0, Math.PI);
        ctx.stroke();
        // Teeth lines (manga style - more defined)
        for (let i = -4; i <= 4; i++) {
            ctx.beginPath();
            ctx.moveTo(x + i * 3, y + 9);
            ctx.lineTo(x + i * 3, y + 16);
            ctx.stroke();
        }
    } else if (character.id === 'jinbe') {
        // Jinbe's face - EXACT manga style (fish-man features)
        // Round eyes (manga style - larger)
        ctx.beginPath();
        ctx.arc(x - 7, y - 5, 5, 0, Math.PI * 2);
        ctx.arc(x + 7, y - 5, 5, 0, Math.PI * 2);
        ctx.fill();
        // Eye shine
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x - 5, y - 6, 2.5, 0, Math.PI * 2);
        ctx.arc(x + 9, y - 6, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        // Fish-man nose (manga style)
        ctx.beginPath();
        ctx.arc(x, y + 2, 2, 0, Math.PI * 2);
        ctx.fill();
        // Serious mouth (manga style)
        ctx.beginPath();
        ctx.moveTo(x - 7, y + 8);
        ctx.lineTo(x + 7, y + 8);
        ctx.stroke();
        ctx.lineWidth = 2.5;
        ctx.lineWidth = 2;
    }
}

function drawDetailedStrawHat(ctx, x, y) {
    ctx.fillStyle = '#ffd700';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#d4af37';
    ctx.beginPath();
    ctx.arc(x, y + 5, 25, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawDetailedBandana(ctx, x, y, color) {
    ctx.fillStyle = color;
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, 22, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawDetailedChopperHat(ctx, x, y) {
    ctx.fillStyle = '#ff0000';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, 28, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawDetailedTopHat(ctx, x, y) {
    ctx.fillStyle = '#000000';
    ctx.strokeStyle = '#333';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
}

function drawZoroSwords(ctx) {
    // Zoro's three swords (detailed One Piece Odyssey style)
    ctx.strokeStyle = '#8b4513';
    ctx.fillStyle = '#654321';
    ctx.lineWidth = 3;
    
    // Wado Ichimonji (left)
    ctx.beginPath();
    ctx.moveTo(-25, 5);
    ctx.lineTo(-25, -15);
    ctx.stroke();
    ctx.fillStyle = '#c0c0c0';
    ctx.beginPath();
    ctx.arc(-25, -15, 2, 0, Math.PI * 2);
    ctx.fill();
    
    // Sandai Kitetsu (center)
    ctx.strokeStyle = '#8b4513';
    ctx.beginPath();
    ctx.moveTo(0, 5);
    ctx.lineTo(0, -15);
    ctx.stroke();
    ctx.fillStyle = '#c0c0c0';
    ctx.beginPath();
    ctx.arc(0, -15, 2, 0, Math.PI * 2);
    ctx.fill();
    
    // Shusui (right)
    ctx.strokeStyle = '#654321';
    ctx.beginPath();
    ctx.moveTo(25, 5);
    ctx.lineTo(25, -15);
    ctx.stroke();
    ctx.fillStyle = '#c0c0c0';
    ctx.beginPath();
    ctx.arc(25, -15, 2, 0, Math.PI * 2);
    ctx.fill();
}

function drawEnemy(ctx, enemy) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    
    ctx.fillStyle = enemy.color;
    ctx.beginPath();
    ctx.arc(enemy.x - 10, enemy.y + 20, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 10, enemy.y + 20, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(enemy.x - 10, enemy.y + 10, 6, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 10, enemy.y + 10, 6, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(enemy.x - 10, enemy.y, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 10, enemy.y, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(enemy.x, enemy.y - 10, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(enemy.x - 18, enemy.y - 15, 7, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 18, enemy.y - 15, 7, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(enemy.x - 18, enemy.y - 5, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 18, enemy.y - 5, 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = '#ffcc99';
    ctx.beginPath();
    ctx.arc(enemy.x - 18, enemy.y + 2, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(enemy.x + 18, enemy.y + 2, 4, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = '#ffcc99';
    ctx.beginPath();
    ctx.arc(enemy.x, enemy.y - 30, 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = enemy.hairColor || '#000000';
    ctx.beginPath();
    ctx.arc(enemy.x, enemy.y - 40, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.strokeStyle = '#000';
    ctx.fillStyle = '#000';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.arc(enemy.x - 5, enemy.y - 38, 2, 0, Math.PI * 2);
    ctx.arc(enemy.x + 5, enemy.y - 38, 2, 0, Math.PI * 2);
    ctx.fill();
    
    ctx.beginPath();
    ctx.moveTo(enemy.x - 4, enemy.y - 30);
    ctx.lineTo(enemy.x + 4, enemy.y - 30);
    ctx.stroke();
    
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 12px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    ctx.strokeText(enemy.name, enemy.x, enemy.y - 55);
    ctx.fillText(enemy.name, enemy.x, enemy.y - 55);
}

function drawBoss(ctx, boss) {
    const island = ISLANDS[gameState.currentIsland];
    if (!island) return;
    
    // Draw boss based on type
    if (boss.name === 'Arlong') {
        drawArlong(ctx, boss);
    } else if (boss.name === 'Crocodile') {
        drawCrocodile(ctx, boss);
    } else if (boss.name === 'Rob Lucci') {
        drawRobLucci(ctx, boss);
    } else if (boss.name === 'Akainu') {
        drawAkainu(ctx, boss);
    } else if (boss.name === 'Doflamingo') {
        drawDoflamingo(ctx, boss);
    } else if (boss.name === 'Big Mom') {
        drawBigMom(ctx, boss);
    } else if (boss.name === 'Kaido') {
        drawKaido(ctx, boss);
    } else {
        drawGenericBoss(ctx, boss, island);
    }
}

function drawArlong(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (blue fish-man)
    ctx.fillStyle = '#0066cc';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head (fish-man with saw nose)
    ctx.fillStyle = '#0066cc';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Saw nose (Arlong's signature)
    ctx.fillStyle = '#ffd700';
    ctx.beginPath();
    ctx.ellipse(boss.x, boss.y - 40, 8, 20, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Eyes
    ctx.fillStyle = '#000';
    ctx.beginPath();
    ctx.arc(boss.x - 10, boss.y - 60, 4, 0, Math.PI * 2);
    ctx.arc(boss.x + 10, boss.y - 60, 4, 0, Math.PI * 2);
    ctx.fill();
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function drawCrocodile(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (brown coat)
    ctx.fillStyle = '#8b4513';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head
    ctx.fillStyle = '#d4a574';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Hook hand (Crocodile's signature)
    ctx.fillStyle = '#c0c0c0';
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 5, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(boss.x - 35, boss.y - 13);
    ctx.lineTo(boss.x - 45, boss.y - 20);
    ctx.lineTo(boss.x - 35, boss.y - 5);
    ctx.fill();
    
    // Scar
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(boss.x - 15, boss.y - 50);
    ctx.lineTo(boss.x + 15, boss.y - 50);
    ctx.stroke();
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function drawRobLucci(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (dark suit)
    ctx.fillStyle = '#654321';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head
    ctx.fillStyle = '#d4a574';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Leopard spots
    ctx.fillStyle = '#8b4513';
    for (let i = 0; i < 8; i++) {
        const angle = (i / 8) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(boss.x + Math.cos(angle) * 20, boss.y - 15 + Math.sin(angle) * 20, 4, 0, Math.PI * 2);
        ctx.fill();
    }
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function drawAkainu(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (red marine coat)
    ctx.fillStyle = '#ff0000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head
    ctx.fillStyle = '#d4a574';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Lava effects
    ctx.fillStyle = '#ff6600';
    for (let i = 0; i < 6; i++) {
        ctx.beginPath();
        ctx.arc(boss.x - 20 + i * 8, boss.y - 10, 5, 0, Math.PI * 2);
        ctx.fill();
    }
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function drawDoflamingo(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (pink coat)
    ctx.fillStyle = '#ff00ff';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head
    ctx.fillStyle = '#d4a574';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Blonde hair
    ctx.fillStyle = '#ffff00';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 70, 25, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Sunglasses
    ctx.fillStyle = '#000';
    ctx.fillRect(boss.x - 20, boss.y - 60, 40, 8);
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function drawBigMom(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (pink, large)
    ctx.fillStyle = '#ff69b4';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 10, 35, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms
    ctx.beginPath();
    ctx.arc(boss.x - 40, boss.y - 15, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 40, boss.y - 15, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head (large)
    ctx.fillStyle = '#ffcc99';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 60, 38, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Pink hair
    ctx.fillStyle = '#ff69b4';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 85, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Crown
    ctx.fillStyle = '#ffd700';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 95, 15, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 110);
    ctx.fillText(boss.name, boss.x, boss.y - 110);
}

function drawKaido(ctx, boss) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    // Body (dark red, massive)
    ctx.fillStyle = '#8b0000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 5, 40, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Arms (massive)
    ctx.beginPath();
    ctx.arc(boss.x - 45, boss.y - 10, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 45, boss.y - 10, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Head (massive)
    ctx.fillStyle = '#8b0000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 65, 42, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Horns (dragon)
    ctx.fillStyle = '#654321';
    ctx.beginPath();
    ctx.arc(boss.x - 15, boss.y - 85, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 15, boss.y - 85, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Beard
    ctx.fillStyle = '#000000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 45, 20, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    // Name
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 22px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 115);
    ctx.fillText(boss.name, boss.x, boss.y - 115);
}

function drawGenericBoss(ctx, boss, island) {
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 3;
    
    ctx.fillStyle = island.bossColor || '#ff0000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 15, 30, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(boss.x - 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(boss.x + 35, boss.y - 20, 12, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = '#ffcc99';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 55, 32, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = island.bossHair || '#000000';
    ctx.beginPath();
    ctx.arc(boss.x, boss.y - 70, 25, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 20px Arial';
    ctx.textAlign = 'center';
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeText(boss.name, boss.x, boss.y - 95);
    ctx.fillText(boss.name, boss.x, boss.y - 95);
}

function loadIsland(island) {
    gameState.currentIsland = island.id - 1;
    gameState.isInCombat = false;
    gameState.isPlayerTurn = true;
    gameState.enemiesDefeated = 0;
    
    document.getElementById('islandComplete').classList.add('hidden');
    document.getElementById('combatUI').classList.add('hidden');
    
    document.getElementById('currentIsland').textContent = `Island ${island.id}: ${island.name}`;
    
    spawnBoss(island);
    spawnEnemies();
    
    updateHUD();
    
    const messageEl = document.getElementById('message');
    if (messageEl) {
        messageEl.textContent = `Welcome to ${island.name}! Defeat all ${gameState.totalEnemies} enemies to unlock the boss!`;
        messageEl.className = 'message info';
        messageEl.classList.remove('hidden');
        setTimeout(() => messageEl.classList.add('hidden'), 5000);
    }
}

function spawnBoss(island) {
    gameState.currentBoss = {
        x: gameState.canvas.width - 150,
        y: gameState.canvas.height - 200,
        name: island.boss,
        hp: island.bossHP,
        maxHP: island.bossHP,
        attack: 20 + gameState.currentIsland * 5,
        berryReward: island.berryReward,
        locked: true,
        bossType: island.bossType,
        bossColor: island.bossColor,
        bossHair: island.bossHair
    };
}

function spawnEnemies() {
    gameState.enemies = [];
    gameState.totalEnemies = 5 + gameState.currentIsland * 2;
    gameState.enemiesRemaining = gameState.totalEnemies;
    
    for (let i = 0; i < gameState.totalEnemies; i++) {
        const enemyType = ENEMIES[Math.floor(Math.random() * ENEMIES.length)];
        gameState.enemies.push({
            x: 200 + (i % 5) * 150,
            y: 200 + Math.floor(i / 5) * 150,
            ...enemyType,
            maxHP: enemyType.hp
        });
    }
}

function setupGameControls() {
    const keys = {};
    
    window.addEventListener('keydown', (e) => {
        keys[e.key.toLowerCase()] = true;
        
        if (e.key === 'Tab') {
            e.preventDefault();
            const menu = document.getElementById('gameMenu');
            menu.classList.toggle('hidden');
        }
    });
    
    window.addEventListener('keyup', (e) => {
        keys[e.key.toLowerCase()] = false;
    });
    
    setInterval(() => {
        if (gameState.isInCombat) return;
        
        const speed = 5;
        if (keys['w'] || keys['W']) gameState.player.y = Math.max(50, gameState.player.y - speed);
        if (keys['s'] || keys['S']) gameState.player.y = Math.min(gameState.canvas.height - 150, gameState.player.y + speed);
        if (keys['a'] || keys['A']) gameState.player.x = Math.max(50, gameState.player.x - speed);
        if (keys['d'] || keys['D']) gameState.player.x = Math.min(gameState.canvas.width - 50, gameState.player.x + speed);
        
        // Check enemy collision
        gameState.enemies.forEach((enemy, index) => {
            const dist = Math.sqrt(Math.pow(gameState.player.x - enemy.x, 2) + Math.pow(gameState.player.y - enemy.y, 2));
            if (dist < 80 && !gameState.isInCombat) {
                startBossBattle(enemy);
            }
        });
        
        // Check boss collision
        if (gameState.currentBoss && !gameState.currentBoss.locked) {
            const dist = Math.sqrt(Math.pow(gameState.player.x - gameState.currentBoss.x, 2) + Math.pow(gameState.player.y - gameState.currentBoss.y, 2));
            if (dist < 100 && !gameState.isInCombat) {
                startBossBattle(gameState.currentBoss);
            }
        }
    }, 16);
}

function startBossBattle(target) {
    gameState.isInCombat = true;
    gameState.isInCombatWithBoss = (target === gameState.currentBoss);
    gameState.currentEnemy = target;
    gameState.currentBoss = target;
    gameState.isPlayerTurn = true;
    
    document.getElementById('combatUI').classList.remove('hidden');
    updateCombatUI();
}

function updateCombatUI() {
    const target = gameState.isInCombatWithBoss ? gameState.currentBoss : gameState.currentEnemy;
    document.getElementById('bossName').textContent = target.name;
    const percent = (target.hp / target.maxHP) * 100;
    document.getElementById('bossHealthBar').style.width = percent + '%';
    document.getElementById('bossHPText').textContent = `${Math.floor(target.hp)}/${target.maxHP}`;
    updateTurnIndicator();
}

function updateTurnIndicator() {
    document.getElementById('turnIndicator').textContent = gameState.isPlayerTurn ? 'Your Turn' : 'Enemy Turn';
    const buttons = document.querySelectorAll('.combat-btn');
    buttons.forEach(btn => btn.disabled = !gameState.isPlayerTurn);
}

document.getElementById('attackBtn').addEventListener('click', performAttack);
document.getElementById('specialBtn').addEventListener('click', useSpecialMove);
document.getElementById('defendBtn').addEventListener('click', defend);
document.getElementById('itemBtn').addEventListener('click', () => {
    showGameMessage('Items coming soon!', 'info');
});

function performAttack() {
    if (!gameState.isPlayerTurn) return;
    
    const target = gameState.isInCombatWithBoss ? gameState.currentBoss : gameState.currentEnemy;
    const damage = gameState.selectedCharacter.attack + Math.floor(Math.random() * 10);
    target.hp = Math.max(0, target.hp - damage);
    
    showGameMessage(`${gameState.selectedCharacter.name} attacks for ${damage} damage!`, 'info');
    updateCombatUI();
    
    if (target.hp <= 0) {
        if (gameState.isInCombatWithBoss) {
            defeatBoss();
        } else {
            defeatEnemy();
        }
    } else {
        gameState.isPlayerTurn = false;
        setTimeout(enemyTurn, 1500);
    }
}

function useSpecialMove() {
    if (!gameState.isPlayerTurn) return;
    
    const moves = MOVES[gameState.selectedCharacter.id] || [];
    if (moves.length === 0) {
        showGameMessage('No special moves available!', 'error');
        return;
    }
    
    // Show special moves menu
    showSpecialMovesMenu(moves);
}

function showSpecialMovesMenu(moves) {
    const menu = document.getElementById('specialMovesMenu');
    const list = document.getElementById('specialMovesList');
    list.innerHTML = '';
    
    moves.forEach((move, index) => {
        const moveBtn = document.createElement('button');
        moveBtn.className = 'combat-btn move-option';
        moveBtn.innerHTML = `
            <div><strong>${move.name}</strong></div>
            <div style="font-size: 0.9em;">${move.description}</div>
            <div style="font-size: 0.8em; color: #ffd700;">Damage: ${move.damage} | Key: ${move.key}</div>
        `;
        moveBtn.addEventListener('click', () => {
            executeSpecialMove(move);
            menu.classList.add('hidden');
        });
        list.appendChild(moveBtn);
    });
    
    menu.classList.remove('hidden');
}

function executeSpecialMove(move) {
    if (!gameState.isPlayerTurn) return;
    
    const target = gameState.isInCombatWithBoss ? gameState.currentBoss : gameState.currentEnemy;
    
    if (move.type === 'buff') {
        // Buff moves
        if (move.name.includes('Gear Second') || move.name.includes('Speed')) {
            gameState.selectedCharacter.speed += 10;
            showGameMessage(`${gameState.selectedCharacter.name} uses ${move.name}! Speed increased!`, 'success');
        } else if (move.name.includes('Defense') || move.name.includes('Guard')) {
            showGameMessage(`${gameState.selectedCharacter.name} uses ${move.name}! Defense increased!`, 'success');
        } else if (move.name.includes('Revive')) {
            gameState.playerHP = Math.min(gameState.maxHP, gameState.playerHP + 50);
            showGameMessage(`${gameState.selectedCharacter.name} uses ${move.name}! HP restored!`, 'success');
            updateHUD();
        }
    } else if (move.type === 'heal') {
        gameState.playerHP = Math.min(gameState.maxHP, gameState.playerHP + 30);
        showGameMessage(`${gameState.selectedCharacter.name} uses ${move.name}! HP restored!`, 'success');
        updateHUD();
    } else {
        // Damage moves
        const damage = move.damage + Math.floor(Math.random() * 15);
        target.hp = Math.max(0, target.hp - damage);
        
        showGameMessage(`${gameState.selectedCharacter.name} uses ${move.name} for ${damage} damage!`, 'info');
        updateCombatUI();
        
        if (target.hp <= 0) {
            if (gameState.isInCombatWithBoss) {
                defeatBoss();
            } else {
                defeatEnemy();
            }
            return;
        }
    }
    
    gameState.isPlayerTurn = false;
    setTimeout(enemyTurn, 1500);
}

document.getElementById('cancelMovesBtn').addEventListener('click', () => {
    document.getElementById('specialMovesMenu').classList.add('hidden');
});

function defend() {
    if (!gameState.isPlayerTurn) return;
    showGameMessage(`${gameState.selectedCharacter.name} defends!`, 'info');
    gameState.isPlayerTurn = false;
    setTimeout(enemyTurn, 1500);
}

function enemyTurn() {
    const target = gameState.isInCombatWithBoss ? gameState.currentBoss : gameState.currentEnemy;
    const damage = target.attack + Math.floor(Math.random() * 5);
    gameState.playerHP = Math.max(0, gameState.playerHP - damage);
    
    showGameMessage(`${target.name} attacks for ${damage} damage!`, 'error');
    updateHUD();
    
    if (gameState.playerHP <= 0) {
        gameOver();
    } else {
        gameState.isPlayerTurn = true;
        updateTurnIndicator();
    }
}

function defeatEnemy() {
    const enemy = gameState.currentEnemy;
    gameState.berries += enemy.berryReward;
    gameState.enemiesDefeated++;
    gameState.enemiesRemaining--;
    
    gameState.enemies = gameState.enemies.filter(e => e !== enemy);
    
    showGameMessage(`Defeated ${enemy.name}! Gained ${enemy.berryReward} Berries!`, 'success');
    
    if (gameState.enemiesRemaining === 0 && gameState.currentBoss) {
        gameState.currentBoss.locked = false;
        showGameMessage('Boss unlocked! Defeat the boss to complete the island!', 'success');
    }
    
    gameState.isInCombat = false;
    document.getElementById('combatUI').classList.add('hidden');
    updateHUD();
}

function defeatBoss() {
    const boss = gameState.currentBoss;
    gameState.berries += boss.berryReward;
    
    showGameMessage(`Defeated ${boss.name}! Gained ${boss.berryReward} Berries!`, 'success');
    
    if (gameState.enemiesRemaining === 0) {
        showIslandComplete();
    } else {
        showGameMessage('Defeat all enemies first!', 'error');
        gameState.currentBoss.locked = true;
    }
    
    gameState.isInCombat = false;
    document.getElementById('combatUI').classList.add('hidden');
    updateHUD();
}

function showIslandComplete() {
    const island = ISLANDS[gameState.currentIsland];
    document.getElementById('islandCompleteText').textContent = 
        `You completed ${island.name}! Earned ${island.berryReward} Berries!`;
    document.getElementById('islandComplete').classList.remove('hidden');
}

document.getElementById('nextIslandBtn').addEventListener('click', () => {
    gameState.currentIsland++;
    if (gameState.currentIsland >= ISLANDS.length) {
        showGameMessage('Congratulations! You completed all islands!', 'success');
        return;
    }
    
    // One Piece Odyssey: Health regeneration and increase on new island
    const hpIncrease = 20 + (gameState.currentIsland * 10); // More HP each island
    gameState.maxHP += hpIncrease;
    gameState.playerHP = gameState.maxHP; // Full restore + increase
    
    showGameMessage(`Health restored to full! Max HP increased by ${hpIncrease}! (New Max: ${gameState.maxHP})`, 'success');
    
    document.getElementById('islandComplete').classList.add('hidden');
    loadIsland(ISLANDS[gameState.currentIsland]);
});

function updateHUD() {
    if (!gameState.selectedCharacter) return;
    const percent = (gameState.playerHP / gameState.maxHP) * 100;
    document.getElementById('healthBar').style.width = percent + '%';
    document.getElementById('healthText').textContent = `${Math.floor(gameState.playerHP)}/${gameState.maxHP}`;
    document.getElementById('berryAmount').textContent = gameState.berries.toLocaleString();
    
    const remaining = gameState.enemiesRemaining;
    const bossIndicator = document.getElementById('bossIndicator');
    if (bossIndicator) {
        if (remaining > 0) {
            bossIndicator.textContent = `⚠️ ${remaining} enemies remaining (Boss locked)`;
            bossIndicator.style.display = 'block';
        } else if (gameState.currentBoss && !gameState.currentBoss.locked) {
            bossIndicator.textContent = `⚠️ BOSS: ${gameState.currentBoss.name} ⚠️`;
            bossIndicator.style.display = 'block';
        } else {
            bossIndicator.style.display = 'none';
        }
    }
}

function showGameMessage(text, type = 'info') {
    const messageEl = document.getElementById('message');
    messageEl.textContent = text;
    messageEl.className = `message ${type}`;
    messageEl.classList.remove('hidden');
    setTimeout(() => messageEl.classList.add('hidden'), 3000);
}

function gameOver() {
    alert('Game Over! You were defeated...');
    location.reload();
}

// Menu handlers
document.getElementById('resumeBtn').addEventListener('click', () => {
    document.getElementById('gameMenu').classList.add('hidden');
});

document.getElementById('changeClothesBtn').addEventListener('click', () => {
    document.getElementById('gameMenu').classList.add('hidden');
    document.getElementById('changeClothesMenu').classList.remove('hidden');
    setupInGameClothing();
});

document.getElementById('quitBtn').addEventListener('click', () => {
    if (confirm('Quit to main menu?')) {
        location.reload();
    }
});

function setupInGameClothing() {
    setupClothingGrid('shirtGridInGame', 'shirt');
    setupClothingGrid('pantsGridInGame', 'pants');
    setupClothingGrid('hatGridInGame', 'hat');
    
    const canvas = document.getElementById('outfitPreview');
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    const character = gameState.selectedCharacter;
    const shirtId = gameState.currentOutfit.shirt || character.defaultShirt;
    const pantsId = gameState.currentOutfit.pants || character.defaultPants;
    const hatId = gameState.currentOutfit.hat || character.defaultHat;
    const shirtColor = CLOTHES.find(c => c.id === shirtId)?.color || character.shirtColor;
    const pantsColor = CLOTHES.find(c => c.id === pantsId)?.color || character.pantsColor;
    
    drawOnePieceCharacter(ctx, character, 100, 150, shirtId, pantsId, hatId, shirtColor, pantsColor);
}

document.getElementById('confirmOutfitBtn').addEventListener('click', () => {
    gameState.currentOutfit = { ...gameState.selectedClothes };
    document.getElementById('changeClothesMenu').classList.add('hidden');
    showGameMessage('Outfit changed!', 'success');
});

document.getElementById('cancelOutfitBtn').addEventListener('click', () => {
    document.getElementById('changeClothesMenu').classList.add('hidden');
});

// Initialize
init();

