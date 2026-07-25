const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const characters = [
    {name: 'Blackbeard', health: 150, speed: 200, jump: 400, attack: 25, special: 50, color: '#8B4513'},
    {name: 'Redbeard', health: 120, speed: 250, jump: 450, attack: 20, special: 45, color: '#DC143C'},
    {name: 'Boneface', health: 100, speed: 220, jump: 420, attack: 30, special: 60, color: '#F5F5DC'},
    {name: 'Scarface', health: 130, speed: 230, jump: 410, attack: 22, special: 48, color: '#8B0000'},
    {name: 'Hookhand', health: 140, speed: 210, jump: 390, attack: 28, special: 55, color: '#2F4F4F'},
    {name: 'Patch', health: 110, speed: 240, jump: 430, attack: 18, special: 42, color: '#FFD700'},
    {name: 'Grim', health: 160, speed: 190, jump: 380, attack: 32, special: 65, color: '#000000'},
    {name: 'Swift', health: 90, speed: 280, jump: 480, attack: 15, special: 38, color: '#00CED1'},
    {name: 'Brute', health: 180, speed: 180, jump: 370, attack: 35, special: 70, color: '#8B4513'},
    {name: 'Shadow', health: 100, speed: 260, jump: 440, attack: 24, special: 52, color: '#4B0082'}
];

let currentCharIndex = 0;
let gameStarted = false;
let player = null;
let enemies = [];
let keys = {};
let groundY = 500;

class Player {
    constructor(data) {
        this.name = data.name;
        this.x = 200;
        this.y = groundY;
        this.width = 40;
        this.height = 60;
        this.health = data.health;
        this.maxHealth = data.health;
        this.speed = data.speed / 10;
        this.jumpForce = data.jump / 10;
        this.attackDamage = data.attack;
        this.specialDamage = data.special;
        this.color = data.color;
        this.velocityY = 0;
        this.onGround = false;
        this.facingRight = true;
        this.isAttacking = false;
        this.attackTimer = 0;
        this.specialTimer = 0;
        this.attackCooldown = 30;
        this.specialCooldown = 180;
    }
    
    update() {
        if (this.isAttacking) {
            this.attackTimer--;
            if (this.attackTimer <= 0) this.isAttacking = false;
            return;
        }
        
        let moveX = 0;
        if (keys['a'] || keys['ArrowLeft']) {
            moveX = -this.speed;
            this.facingRight = false;
        }
        if (keys['d'] || keys['ArrowRight']) {
            moveX = this.speed;
            this.facingRight = true;
        }
        
        this.x += moveX;
        this.x = Math.max(20, Math.min(canvas.width - this.width - 20, this.x));
        
        if ((keys[' '] || keys['w']) && this.onGround) {
            this.velocityY = -this.jumpForce;
            this.onGround = false;
        }
        
        this.velocityY += 0.8;
        this.y += this.velocityY;
        
        if (this.y >= groundY) {
            this.y = groundY;
            this.velocityY = 0;
            this.onGround = true;
        }
        
        if (keys['x'] && this.attackTimer <= 0) {
            this.attack();
        }
        
        if (keys['z'] && this.specialTimer <= 0) {
            this.specialAttack();
        }
        
        this.attackTimer = Math.max(0, this.attackTimer - 1);
        this.specialTimer = Math.max(0, this.specialTimer - 1);
        
        enemies.forEach(enemy => {
            if (this.isAttacking && this.checkCollision(enemy)) {
                enemy.takeDamage(this.attackDamage);
            }
        });
    }
    
    attack() {
        this.isAttacking = true;
        this.attackTimer = this.attackCooldown;
    }
    
    specialAttack() {
        this.isAttacking = true;
        this.attackTimer = this.attackCooldown;
        this.specialTimer = this.specialCooldown;
        enemies.forEach(enemy => {
            if (this.checkCollision(enemy, 80)) {
                enemy.takeDamage(this.specialDamage);
            }
        });
    }
    
    checkCollision(other, range = 60) {
        const dx = other.x - this.x;
        const dy = other.y - this.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        return distance < range;
    }
    
    takeDamage(amount) {
        this.health -= amount;
        if (this.health <= 0) {
            this.health = 0;
            gameOver();
        }
        updateHealthBar();
    }
    
    draw() {
        ctx.save();
        if (!this.facingRight) {
            ctx.scale(-1, 1);
            ctx.translate(-this.x - this.width, 0);
        }
        
        const x = this.facingRight ? this.x : canvas.width - this.x - this.width;
        
        ctx.fillStyle = this.color;
        ctx.fillRect(x, this.y, this.width, this.height);
        
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 3;
        ctx.strokeRect(x, this.y, this.width, this.height);
        
        ctx.fillStyle = '#000';
        ctx.fillRect(x + 10, this.y + 10, 8, 8);
        ctx.fillRect(x + 22, this.y + 10, 8, 8);
        ctx.fillRect(x + 8, this.y + 25, 24, 12);
        
        if (this.isAttacking) {
            ctx.strokeStyle = '#FF0000';
            ctx.lineWidth = 4;
            const attackX = this.facingRight ? x + this.width : x - 40;
            ctx.beginPath();
            ctx.moveTo(attackX, this.y + 20);
            ctx.lineTo(attackX + 40, this.y);
            ctx.lineTo(attackX + 40, this.y + 40);
            ctx.closePath();
            ctx.stroke();
        }
        
        ctx.restore();
    }
}

class Enemy {
    constructor(x, type) {
        this.x = x;
        this.y = groundY;
        this.width = 40;
        this.height = 60;
        this.type = type;
        this.facingRight = false;
        this.isAttacking = false;
        this.attackTimer = 0;
        
        const types = [
            {health: 100, speed: 2, damage: 15, color: '#8B4513'},
            {health: 80, speed: 2.5, damage: 12, color: '#F5F5DC'},
            {health: 150, speed: 1.5, damage: 25, color: '#FFD700'}
        ];
        
        const data = types[type];
        this.health = data.health;
        this.maxHealth = data.health;
        this.speed = data.speed;
        this.damage = data.damage;
        this.color = data.color;
    }
    
    update() {
        if (!player) return;
        
        const dx = player.x - this.x;
        const distance = Math.abs(dx);
        
        if (distance < 80 && this.attackTimer <= 0) {
            this.attack();
        } else if (distance < 300) {
            this.x += Math.sign(dx) * this.speed;
            this.facingRight = dx > 0;
        }
        
        this.attackTimer = Math.max(0, this.attackTimer - 1);
        
        if (this.isAttacking && this.checkCollision(player)) {
            player.takeDamage(this.damage);
        }
    }
    
    attack() {
        this.isAttacking = true;
        this.attackTimer = 90;
        setTimeout(() => this.isAttacking = false, 300);
    }
    
    checkCollision(other) {
        return Math.abs(this.x - other.x) < 50 && Math.abs(this.y - other.y) < 50;
    }
    
    takeDamage(amount) {
        this.health -= amount;
        if (this.health <= 0) {
            const index = enemies.indexOf(this);
            if (index > -1) enemies.splice(index, 1);
        }
    }
    
    draw() {
        ctx.save();
        if (!this.facingRight) {
            ctx.scale(-1, 1);
            ctx.translate(-this.x - this.width, 0);
        }
        
        const x = this.facingRight ? this.x : canvas.width - this.x - this.width;
        
        ctx.fillStyle = this.color;
        ctx.fillRect(x, this.y, this.width, this.height);
        
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 3;
        ctx.strokeRect(x, this.y, this.width, this.height);
        
        ctx.fillStyle = '#000';
        ctx.fillRect(x + 10, this.y + 10, 8, 8);
        ctx.fillRect(x + 22, this.y + 10, 8, 8);
        ctx.fillRect(x + 8, this.y + 25, 24, 12);
        
        if (this.isAttacking) {
            ctx.strokeStyle = '#FF0000';
            ctx.lineWidth = 4;
            const attackX = this.facingRight ? x + this.width : x - 40;
            ctx.beginPath();
            ctx.moveTo(attackX, this.y + 20);
            ctx.lineTo(attackX + 40, this.y);
            ctx.lineTo(attackX + 40, this.y + 40);
            ctx.closePath();
            ctx.stroke();
        }
        
        ctx.restore();
        
        const healthPercent = this.health / this.maxHealth;
        ctx.fillStyle = '#8B0000';
        ctx.fillRect(this.x - 10, this.y - 15, 60, 5);
        ctx.fillStyle = '#00FF00';
        ctx.fillRect(this.x - 10, this.y - 15, 60 * healthPercent, 5);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 1;
        ctx.strokeRect(this.x - 10, this.y - 15, 60, 5);
    }
}

function updateCharacterDisplay() {
    const char = characters[currentCharIndex];
    document.getElementById('charName').textContent = char.name;
    document.getElementById('charStats').textContent = 
        `Health: ${char.health} | Speed: ${char.speed} | Attack: ${char.attack} | Special: ${char.special}`;
}

function prevCharacter() {
    currentCharIndex = (currentCharIndex - 1 + characters.length) % characters.length;
    updateCharacterDisplay();
}

function nextCharacter() {
    currentCharIndex = (currentCharIndex + 1) % characters.length;
    updateCharacterDisplay();
}

function selectCharacter() {
    document.getElementById('characterSelect').style.display = 'none';
    document.getElementById('ui').style.display = 'block';
    document.getElementById('controls').style.display = 'block';
    gameStarted = true;
    
    player = new Player(characters[currentCharIndex]);
    enemies = [
        new Enemy(400, 0),
        new Enemy(800, 1),
        new Enemy(1200, 2)
    ];
    
    updateHealthBar();
    gameLoop();
}

function updateHealthBar() {
    if (!player) return;
    const percent = (player.health / player.maxHealth) * 100;
    document.getElementById('healthFill').style.width = percent + '%';
}

function gameOver() {
    ctx.fillStyle = 'rgba(0,0,0,0.8)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#FFF';
    ctx.font = '48px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('GAME OVER', canvas.width / 2, canvas.height / 2);
    ctx.font = '24px Arial';
    ctx.fillText('Refresh to play again', canvas.width / 2, canvas.height / 2 + 50);
}

function gameLoop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#8B4513';
    ctx.fillRect(0, groundY + 60, canvas.width, 100);
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 4;
    ctx.strokeRect(0, groundY + 60, canvas.width, 100);
    
    if (player) {
        player.update();
        player.draw();
    }
    
    enemies.forEach(enemy => {
        enemy.update();
        enemy.draw();
    });
    
    if (enemies.length === 0 && gameStarted) {
        ctx.fillStyle = 'rgba(0,0,0,0.8)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#FFF';
        ctx.font = '48px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('VICTORY!', canvas.width / 2, canvas.height / 2);
        ctx.font = '24px Arial';
        ctx.fillText('All enemies defeated!', canvas.width / 2, canvas.height / 2 + 50);
        return;
    }
    
    requestAnimationFrame(gameLoop);
}

document.addEventListener('keydown', (e) => {
    keys[e.key.toLowerCase()] = true;
});

document.addEventListener('keyup', (e) => {
    keys[e.key.toLowerCase()] = false;
});

updateCharacterDisplay();
