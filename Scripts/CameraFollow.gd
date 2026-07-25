extends Camera2D

@export var target: Node2D
@export var follow_speed: float = 5.0
@export var offset: Vector2 = Vector2.ZERO

func _ready():
	if not target:
		_find_player()

func _process(delta):
	if target:
		var target_position = target.global_position + offset
		global_position = global_position.lerp(target_position, follow_speed * delta)

func _find_player():
	var players = get_tree().get_nodes_in_group("player")
	if not players.is_empty():
		target = players[0]
