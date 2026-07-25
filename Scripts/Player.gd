extends CharacterBody2D

signal health_changed(current_health: int, max_health: int)
signal died

var character_data: CharacterData
var health: int
var max_health: int
var speed: float
var jump_force: float
var attack_damage: int
var special_damage: int
var attack_cooldown: float
var special_cooldown: float

var attack_timer: float = 0.0
var special_timer: float = 0.0
var is_attacking: bool = false
var is_special_attacking: bool = false
var facing_right: bool = true

var gravity: float = 980.0
var is_on_ground: bool = false

@onready var sprite: Sprite2D = $Sprite2D
@onready var hat: Sprite2D = $Sprite2D/Hat
@onready var weapon: Sprite2D = $Sprite2D/Weapon
@onready var coat: Sprite2D = $Sprite2D/Coat
@onready var attack_area: Area2D = $AttackArea
@onready var special_area: Area2D = $SpecialArea
@onready var animation_player: AnimationPlayer = $AnimationPlayer
@onready var collision_shape: CollisionShape2D = $CollisionShape2D

func _ready():
	if character_data:
		_setup_character()

func setup(character: CharacterData):
	character_data = character
	_setup_character()

func _setup_character():
	if not character_data:
		return
	
	max_health = character_data.max_health
	health = max_health
	speed = character_data.speed
	jump_force = character_data.jump_force
	attack_damage = character_data.attack_damage
	special_damage = character_data.special_damage
	attack_cooldown = character_data.attack_cooldown
	special_cooldown = character_data.special_cooldown
	
	if ResourceLoader.exists(character_data.sprite_path):
		sprite.texture = load(character_data.sprite_path)
	
	if ResourceLoader.exists(character_data.hat_path):
		hat.texture = load(character_data.hat_path)
		hat.visible = true
	else:
		hat.visible = false
	
	if ResourceLoader.exists(character_data.weapon_path):
		weapon.texture = load(character_data.weapon_path)
		weapon.visible = true
	else:
		weapon.visible = false
	
	if ResourceLoader.exists(character_data.coat_path):
		coat.texture = load(character_data.coat_path)
		coat.visible = true
	else:
		coat.visible = false
	
	health_changed.emit(health, max_health)

func _physics_process(delta):
	attack_timer = max(0.0, attack_timer - delta)
	special_timer = max(0.0, special_timer - delta)
	
	if is_attacking or is_special_attacking:
		velocity.x = 0
	else:
		_handle_movement()
	
	_handle_gravity(delta)
	_handle_jump()
	_handle_attacks()
	
	move_and_slide()
	_update_sprite_direction()

func _handle_movement():
	var direction = 0.0
	
	if Input.is_action_pressed("move_left"):
		direction -= 1.0
	if Input.is_action_pressed("move_right"):
		direction += 1.0
	
	velocity.x = direction * speed

func _handle_gravity(delta):
	if not is_on_floor():
		velocity.y += gravity * delta
	else:
		velocity.y = 0

func _handle_jump():
	if Input.is_action_just_pressed("jump") and is_on_floor():
		velocity.y = -jump_force

func _handle_attacks():
	if Input.is_action_just_pressed("attack") and attack_timer <= 0.0 and not is_attacking and not is_special_attacking:
		_attack()
	
	if Input.is_action_just_pressed("special_attack") and special_timer <= 0.0 and not is_attacking and not is_special_attacking:
		_special_attack()

func _attack():
	is_attacking = true
	attack_timer = attack_cooldown
	
	if animation_player.has_animation(character_data.attack_animation):
		animation_player.play(character_data.attack_animation)
	else:
		animation_player.play("attack")
	
	await get_tree().create_timer(0.2).timeout
	_perform_attack_damage(attack_damage, attack_area)
	await get_tree().create_timer(0.3).timeout
	is_attacking = false

func _special_attack():
	is_special_attacking = true
	special_timer = special_cooldown
	
	if animation_player.has_animation("special_attack"):
		animation_player.play("special_attack")
	else:
		animation_player.play("attack")
	
	await get_tree().create_timer(0.3).timeout
	_perform_attack_damage(special_damage, special_area)
	await get_tree().create_timer(0.5).timeout
	is_special_attacking = false

func _perform_attack_damage(damage: int, area: Area2D):
	var bodies = area.get_overlapping_bodies()
	for body in bodies:
		if body.has_method("take_damage"):
			body.take_damage(damage)

func _update_sprite_direction():
	if velocity.x > 0:
		facing_right = true
	elif velocity.x < 0:
		facing_right = false
	
	if facing_right:
		sprite.scale.x = 1.0
	else:
		sprite.scale.x = -1.0

func take_damage(amount: int):
	health -= amount
	health = max(0, health)
	health_changed.emit(health, max_health)
	
	if health <= 0:
		_die()

func _die():
	died.emit()
	queue_free()

func heal(amount: int):
	health = min(max_health, health + amount)
	health_changed.emit(health, max_health)
