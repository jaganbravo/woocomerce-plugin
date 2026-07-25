extends Control

signal character_selected(character_data: CharacterData)

var characters: Array[CharacterData] = []
var current_index: int = 0
var character_buttons: Array[Button] = []

@onready var character_display: Control = $CharacterDisplay
@onready var character_name_label: Label = $CharacterDisplay/NameLabel
@onready var stats_label: Label = $CharacterDisplay/StatsLabel
@onready var sprite_display: Sprite2D = $CharacterDisplay/SpriteDisplay
@onready var hat_sprite: Sprite2D = $CharacterDisplay/SpriteDisplay/Hat
@onready var weapon_sprite: Sprite2D = $CharacterDisplay/SpriteDisplay/Weapon
@onready var coat_sprite: Sprite2D = $CharacterDisplay/SpriteDisplay/Coat
@onready var prev_button: Button = $Navigation/PrevButton
@onready var next_button: Button = $Navigation/NextButton
@onready var select_button: Button = $Navigation/SelectButton

func _ready():
	_create_characters()
	_update_display()
	prev_button.pressed.connect(_on_prev_button_pressed)
	next_button.pressed.connect(_on_next_button_pressed)
	select_button.pressed.connect(_on_select_button_pressed)

func _create_characters():
	var char1 = CharacterData.new()
	char1.character_name = "Blackbeard"
	char1.max_health = 150
	char1.speed = 200.0
	char1.jump_force = 400.0
	char1.attack_damage = 25
	char1.special_damage = 50
	char1.attack_cooldown = 0.5
	char1.special_cooldown = 3.0
	char1.sprite_path = "res://Assets/Characters/blackbeard.png"
	char1.attack_animation = "slash_heavy"
	char1.hat_path = "res://Assets/Accessories/Hats/hat_black.png"
	char1.weapon_path = "res://Assets/Accessories/Weapons/sword_long.png"
	char1.coat_path = "res://Assets/Accessories/Coats/coat_black.png"
	characters.append(char1)
	
	var char2 = CharacterData.new()
	char2.character_name = "Redbeard"
	char2.max_health = 120
	char2.speed = 250.0
	char2.jump_force = 450.0
	char2.attack_damage = 20
	char2.special_damage = 45
	char2.attack_cooldown = 0.4
	char2.special_cooldown = 2.5
	char2.sprite_path = "res://Assets/Characters/redbeard.png"
	char2.attack_animation = "slash_fast"
	char2.hat_path = "res://Assets/Accessories/Hats/hat_red.png"
	char2.weapon_path = "res://Assets/Accessories/Weapons/sword_short.png"
	char2.coat_path = "res://Assets/Accessories/Coats/coat_red.png"
	characters.append(char2)
	
	var char3 = CharacterData.new()
	char3.character_name = "Boneface"
	char3.max_health = 100
	char3.speed = 220.0
	char3.jump_force = 420.0
	char3.attack_damage = 30
	char3.special_damage = 60
	char3.attack_cooldown = 0.6
	char3.special_cooldown = 4.0
	char3.sprite_path = "res://Assets/Characters/boneface.png"
	char3.attack_animation = "slash_heavy"
	char3.hat_path = "res://Assets/Accessories/Hats/hat_bone.png"
	char3.weapon_path = "res://Assets/Accessories/Weapons/sword_bone.png"
	char3.coat_path = "res://Assets/Accessories/Coats/coat_bone.png"
	characters.append(char3)
	
	var char4 = CharacterData.new()
	char4.character_name = "Scarface"
	char4.max_health = 130
	char4.speed = 230.0
	char4.jump_force = 410.0
	char4.attack_damage = 22
	char4.special_damage = 48
	char4.attack_cooldown = 0.45
	char4.special_cooldown = 2.8
	char4.sprite_path = "res://Assets/Characters/scarface.png"
	char4.attack_animation = "slash_fast"
	char4.hat_path = "res://Assets/Accessories/Hats/hat_scar.png"
	char4.weapon_path = "res://Assets/Accessories/Weapons/sword_curved.png"
	char4.coat_path = "res://Assets/Accessories/Coats/coat_scar.png"
	characters.append(char4)
	
	var char5 = CharacterData.new()
	char5.character_name = "Hookhand"
	char5.max_health = 140
	char5.speed = 210.0
	char5.jump_force = 390.0
	char5.attack_damage = 28
	char5.special_damage = 55
	char5.attack_cooldown = 0.55
	char5.special_cooldown = 3.5
	char5.sprite_path = "res://Assets/Characters/hookhand.png"
	char5.attack_animation = "slash_heavy"
	char5.hat_path = "res://Assets/Accessories/Hats/hat_hook.png"
	char5.weapon_path = "res://Assets/Accessories/Weapons/sword_hook.png"
	char5.coat_path = "res://Assets/Accessories/Coats/coat_hook.png"
	characters.append(char5)
	
	var char6 = CharacterData.new()
	char6.character_name = "Patch"
	char6.max_health = 110
	char6.speed = 240.0
	char6.jump_force = 430.0
	char6.attack_damage = 18
	char6.special_damage = 42
	char6.attack_cooldown = 0.35
	char6.special_cooldown = 2.2
	char6.sprite_path = "res://Assets/Characters/patch.png"
	char6.attack_animation = "slash_fast"
	char6.hat_path = "res://Assets/Accessories/Hats/hat_patch.png"
	char6.weapon_path = "res://Assets/Accessories/Weapons/sword_rapier.png"
	char6.coat_path = "res://Assets/Accessories/Coats/coat_patch.png"
	characters.append(char6)
	
	var char7 = CharacterData.new()
	char7.character_name = "Grim"
	char7.max_health = 160
	char7.speed = 190.0
	char7.jump_force = 380.0
	char7.attack_damage = 32
	char7.special_damage = 65
	char7.attack_cooldown = 0.65
	char7.special_cooldown = 4.5
	char7.sprite_path = "res://Assets/Characters/grim.png"
	char7.attack_animation = "slash_heavy"
	char7.hat_path = "res://Assets/Accessories/Hats/hat_grim.png"
	char7.weapon_path = "res://Assets/Accessories/Weapons/sword_grim.png"
	char7.coat_path = "res://Assets/Accessories/Coats/coat_grim.png"
	characters.append(char7)
	
	var char8 = CharacterData.new()
	char8.character_name = "Swift"
	char8.max_health = 90
	char8.speed = 280.0
	char8.jump_force = 480.0
	char8.attack_damage = 15
	char8.special_damage = 38
	char8.attack_cooldown = 0.3
	char8.special_cooldown = 2.0
	char8.sprite_path = "res://Assets/Characters/swift.png"
	char8.attack_animation = "slash_fast"
	char8.hat_path = "res://Assets/Accessories/Hats/hat_swift.png"
	char8.weapon_path = "res://Assets/Accessories/Weapons/sword_swift.png"
	char8.coat_path = "res://Assets/Accessories/Coats/coat_swift.png"
	characters.append(char8)
	
	var char9 = CharacterData.new()
	char9.character_name = "Brute"
	char9.max_health = 180
	char9.speed = 180.0
	char9.jump_force = 370.0
	char9.attack_damage = 35
	char9.special_damage = 70
	char9.attack_cooldown = 0.7
	char9.special_cooldown = 5.0
	char9.sprite_path = "res://Assets/Characters/brute.png"
	char9.attack_animation = "slash_heavy"
	char9.hat_path = "res://Assets/Accessories/Hats/hat_brute.png"
	char9.weapon_path = "res://Assets/Accessories/Weapons/sword_brute.png"
	char9.coat_path = "res://Assets/Accessories/Coats/coat_brute.png"
	characters.append(char9)
	
	var char10 = CharacterData.new()
	char10.character_name = "Shadow"
	char10.max_health = 100
	char10.speed = 260.0
	char10.jump_force = 440.0
	char10.attack_damage = 24
	char10.special_damage = 52
	char10.attack_cooldown = 0.42
	char10.special_cooldown = 2.6
	char10.sprite_path = "res://Assets/Characters/shadow.png"
	char10.attack_animation = "slash_fast"
	char10.hat_path = "res://Assets/Accessories/Hats/hat_shadow.png"
	char10.weapon_path = "res://Assets/Accessories/Weapons/sword_shadow.png"
	char10.coat_path = "res://Assets/Accessories/Coats/coat_shadow.png"
	characters.append(char10)

func _update_display():
	if characters.is_empty():
		return
	
	var current_char = characters[current_index]
	character_name_label.text = current_char.character_name
	
	var stats_text = "Health: %d\nSpeed: %.0f\nJump: %.0f\nAttack: %d\nSpecial: %d"
	stats_label.text = stats_text % [
		current_char.max_health,
		current_char.speed,
		current_char.jump_force,
		current_char.attack_damage,
		current_char.special_damage
	]
	
	if ResourceLoader.exists(current_char.sprite_path):
		var texture = load(current_char.sprite_path)
		sprite_display.texture = texture
	
	if ResourceLoader.exists(current_char.hat_path):
		var hat_texture = load(current_char.hat_path)
		hat_sprite.texture = hat_texture
		hat_sprite.visible = true
	else:
		hat_sprite.visible = false
	
	if ResourceLoader.exists(current_char.weapon_path):
		var weapon_texture = load(current_char.weapon_path)
		weapon_sprite.texture = weapon_texture
		weapon_sprite.visible = true
	else:
		weapon_sprite.visible = false
	
	if ResourceLoader.exists(current_char.coat_path):
		var coat_texture = load(current_char.coat_path)
		coat_sprite.texture = coat_texture
		coat_sprite.visible = true
	else:
		coat_sprite.visible = false

func _on_prev_button_pressed():
	current_index = (current_index - 1 + characters.size()) % characters.size()
	_update_display()

func _on_next_button_pressed():
	current_index = (current_index + 1) % characters.size()
	_update_display()

func _on_select_button_pressed():
	if not characters.is_empty():
		character_selected.emit(characters[current_index])
