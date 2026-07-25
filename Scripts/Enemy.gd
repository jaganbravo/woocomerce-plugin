extends CharacterBody2D

enum EnemyType {
	PIRATE,
	SKELETON,
	CAPTAIN
}

var enemy_type: EnemyType = EnemyType.PIRATE
var health: int = 100
var max_health: int = 100
var speed: float = 100.0
var attack_damage: int = 15
var attack_range: float = 80.0
var detection_range: float = 300.0
var attack_cooldown: float = 1.5
var attack_timer: float = 0.0
var is_attacking: bool = false

var player: Node2D = null
var facing_right: bool = true

var gravity: float = 980.0

@onready var sprite: Sprite2D = $Sprite2D
@onready var attack_area: Area2D = $AttackArea
@onready var detection_area: Area2D = $DetectionArea
@onready var animation_player: AnimationPlayer = $AnimationPlayer

func _ready():
	_setup_enemy_type()
	detection_area.body_entered.connect(_on_player_detected)
	detection_area.body_exited.connect(_on_player_lost)

func _setup_enemy_type():
	match enemy_type:
		EnemyType.PIRATE:
			max_health = 100
			health = 100
			speed = 100.0
			attack_damage = 15
			attack_range = 80.0
			detection_range = 300.0
			attack_cooldown = 1.5
			if ResourceLoader.exists("res://Assets/Enemies/pirate.png"):
				sprite.texture = load("res://Assets/Enemies/pirate.png")
		EnemyType.SKELETON:
			max_health = 80
			health = 80
			speed = 120.0
			attack_damage = 12
			attack_range = 70.0
			detection_range = 350.0
			attack_cooldown = 1.2
			if ResourceLoader.exists("res://Assets/Enemies/skeleton.png"):
				sprite.texture = load("res://Assets/Enemies/skeleton.png")
		EnemyType.CAPTAIN:
			max_health = 150
			health = 150
			speed = 90.0
			attack_damage = 25
			attack_range = 90.0
			detection_range = 400.0
			attack_cooldown = 2.0
			if ResourceLoader.exists("res://Assets/Enemies/captain.png"):
				sprite.texture = load("res://Assets/Enemies/captain.png")

func _physics_process(delta):
	attack_timer = max(0.0, attack_timer - delta)
	
	if is_attacking:
		velocity.x = 0
	else:
		_handle_ai()
	
	_handle_gravity(delta)
	move_and_slide()
	_update_sprite_direction()

func _handle_gravity(delta):
	if not is_on_floor():
		velocity.y += gravity * delta
	else:
		velocity.y = 0

func _handle_ai():
	if not player:
		velocity.x = 0
		return
	
	var distance_to_player = global_position.distance_to(player.global_position)
	
	if distance_to_player <= attack_range and attack_timer <= 0.0:
		_attack()
	elif distance_to_player <= detection_range:
		_move_towards_player()
	else:
		velocity.x = 0

func _move_towards_player():
	var direction = sign(player.global_position.x - global_position.x)
	velocity.x = direction * speed

func _attack():
	is_attacking = true
	attack_timer = attack_cooldown
	
	if animation_player.has_animation("attack"):
		animation_player.play("attack")
	
	await get_tree().create_timer(0.2).timeout
	_perform_attack()
	await get_tree().create_timer(0.3).timeout
	is_attacking = false

func _perform_attack():
	var bodies = attack_area.get_overlapping_bodies()
	for body in bodies:
		if body.has_method("take_damage") and body != self:
			body.take_damage(attack_damage)

func _update_sprite_direction():
	if velocity.x > 0:
		facing_right = true
	elif velocity.x < 0:
		facing_right = false
	
	if facing_right:
		sprite.scale.x = 1.0
	else:
		sprite.scale.x = -1.0

func _on_player_detected(body):
	if body.has_method("take_damage") and body != self:
		player = body

func _on_player_lost(body):
	if body == player:
		player = null

func take_damage(amount: int):
	health -= amount
	health = max(0, health)
	
	if health <= 0:
		_die()

func _die():
	queue_free()
