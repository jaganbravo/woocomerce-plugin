extends Node

var enemy_scene = preload("res://Scenes/Enemy.tscn")
var enemy_spawn_points: Array[Marker2D] = []

func _ready():
	_find_spawn_points()

func _find_spawn_points():
	var game_scene = get_tree().get_first_node_in_group("game_scene")
	if not game_scene:
		return
	
	for child in game_scene.get_children():
		if child is Marker2D and "EnemySpawn" in child.name:
			enemy_spawn_points.append(child)

func spawn_enemies():
	_find_spawn_points()
	
	var game_scene = get_tree().get_first_node_in_group("game_scene")
	if not game_scene:
		return
	
	for i in range(min(3, enemy_spawn_points.size())):
		if i >= enemy_spawn_points.size():
			break
		
		var spawn_point = enemy_spawn_points[i]
		var enemy = enemy_scene.instantiate()
		enemy.enemy_type = i
		enemy.position = spawn_point.position
		game_scene.add_child(enemy)
