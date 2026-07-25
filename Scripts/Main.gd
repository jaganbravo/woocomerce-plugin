extends Node

var selected_character: CharacterData = null
var player_instance: Node2D = null
var game_manager: Node = null

@onready var character_selection: Control = $CharacterSelection
@onready var game_scene: Node2D = $GameScene
@onready var ui: Control = $UI

func _ready():
	character_selection.character_selected.connect(_on_character_selected)
	game_scene.visible = false
	ui.visible = false
	
	game_manager = Node.new()
	game_manager.set_script(load("res://Scripts/GameManager.gd"))
	add_child(game_manager)

func _on_character_selected(character: CharacterData):
	selected_character = character
	character_selection.visible = false
	_start_game()

func _start_game():
	game_scene.visible = true
	ui.visible = true
	
	var player = game_scene.get_node_or_null("Player")
	if player:
		player.setup(selected_character)
		player_instance = player
		player.health_changed.connect(_on_player_health_changed)
		player.died.connect(_on_player_died)
		
		var camera = game_scene.get_node_or_null("Camera2D")
		if camera:
			camera.target = player
	
	if game_manager:
		game_manager.spawn_enemies()

func _on_player_health_changed(current: int, max_health: int):
	var health_bar = ui.get_node_or_null("HealthBar")
	if health_bar:
		health_bar.value = (float(current) / float(max_health)) * 100.0

func _on_player_died():
	game_scene.visible = false
	ui.visible = false
	character_selection.visible = true
