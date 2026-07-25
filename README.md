# Pirate Fighter - 2D Anime-Style Action Game

A colorful anime-style pirate action fighting game built with Godot Engine 4.2.

## Project Structure

```
├── Scenes/           # All .tscn scene files
│   ├── Main.tscn
│   ├── CharacterSelection.tscn
│   ├── Player.tscn
│   ├── Enemy.tscn
│   └── GameScene.tscn
├── Scripts/          # All GDScript files
│   ├── Main.gd
│   ├── CharacterData.gd
│   ├── CharacterSelection.gd
│   ├── Player.gd
│   ├── Enemy.gd
│   ├── CameraFollow.gd
│   └── GameManager.gd
├── Assets/           # Game assets
│   ├── Characters/   # 10 pirate character sprites
│   ├── Accessories/  # Hats, Weapons, Coats
│   └── Enemies/      # Enemy sprites
└── UI/               # UI elements

```

## Features

- 10 playable pirate characters with unique stats
- Character selection screen
- Accessory system (hat, weapon, coat) that changes character appearance
- Core gameplay: movement, jump, basic attack, special attack
- Health system
- 3 enemy types with AI
- Smooth follow camera
- Colorful anime-style visuals with cel shading

## Controls

- **A/Left Arrow**: Move left
- **D/Right Arrow**: Move right
- **Space/W**: Jump
- **X/Left Mouse**: Basic attack
- **Z/Right Mouse**: Special attack

## Setup

- WordPress 5.8 or higher
- PHP 8.3 or higher
- WooCommerce 5.0 or higher

## Character Data

Each character has:
- Unique health, speed, jump force
- Unique attack and special attack damage
- Unique cooldown timers
- Unique sprite and accessories
