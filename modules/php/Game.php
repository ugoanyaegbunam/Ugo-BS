<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * UgoBS implementation : © Ugo Anyaegbunam anyaegbunamu@carleton.edu
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */

namespace Bga\Games\UgoBS;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

use \Bga\GameFramework\Actions\Types\IntArrayParam;

// Suit and card data, added additional classes (suit_N) for custom CSS
const COLORS = [
    1 => ['name' => '<span style="color:black" class="suit_1">♠</span>'],
    2 => ['name' => '<span style="color:red" class="suit_2">♥</span>'],
    3 => ['name' => '<span style="color:black" class="suit_3">♣</span>'],
    4 => ['name' => '<span style="color:red" class="suit_4">♦</span>'],
];

const VALUES_LABEL = [
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => 'J',
    12 => 'Q',
    13 => 'K',
    14 => 'A'
];

const DECISIONS = [
    0 => 'passBSCall',
    1 => 'callBS'
];

const OUTCOMES =[
    40 => 'wrong',
    41 => 'right'
];

class Game extends \Table
{
    private static array $CARD_TYPES;
    private $cards;
    private $playerActions = [];


    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "turnCard" => 154,
            "numCardsPlayedLast" => 50,
            "lastBSCaller" => 99,
            "lastPlayer" => 60,
            "receiver" => 72,
            "outcome" => 40
        ]);        

        self::$CARD_TYPES = [
            1 => [
                "card_name" => clienttranslate('Troll'), // ...
            ],
            2 => [
                "card_name" => clienttranslate('Goblin'), // ...
            ],
            // ...
        ];
        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");

    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actPlayCard(#[IntArrayParam] array $card_ids): void
    {
        // // Retrieve the active player ID.
        // $player_id = (int)$this->getActivePlayerId();

        // // check input values
        // $args = $this->argPlayerTurn();
        // $playableCardsIds = $args['playableCardsIds'];
        // if (!in_array($card_id, $playableCardsIds)) {
        //     throw new \BgaUserException('Invalid card choice');
        // }

        // // Add your game logic to play a card here.
        // $card_name = self::$CARD_TYPES[$card_id]['card_name'];

        // // Notify all players about the card played.
        // $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
        //     "player_id" => $player_id,
        //     "player_name" => $this->getActivePlayerName(),
        //     "card_name" => $card_name,
        //     "card_id" => $card_id,
        //     "i18n" => ['card_name'],
        // ]);

        // // at the end of the action, move to the next state
        // $this->gamestate->nextState("playCard");
        $player_id = $this->getActivePlayerId();

        if (count($card_ids) > 4) throw new \BgaUserException($this->_("You may play at most 4 cards"));

        foreach ($card_ids as $card) {
            $this->cards->insertCard($card, 'cardsontable', $player_id);
        }

        // $this->cards->moveCards($card_ids, 'cardsontable', $player_id);
        // XXX check rules here
        $numCards = count($card_ids);
        // And notify
        $this->notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${numCards} ${turnCard}'), array (
                'i18n' => array ('turnCard','numCards', 'cards' ),'player_id' => $player_id,
                'player_name' => $this->getActivePlayerName(),
                'cards' => $this->cards->getCardsOnTop($numCards, "cardsontable"),
                'numCards' => $numCards,
                'turnCard' => VALUES_LABEL[$this->getGameStateValue("turnCard")/11] ));
        $this->setGameStateValue("numCardsPlayedLast", $numCards);
        $this->setGameStateValue("lastPlayer", $player_id);

        // Next player
        $this->gamestate->nextState('offerBSCall');
        }

    public function actPass(): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Notify all players about the choice to pass.
        $this->notifyAllPlayers("cardPlayed", clienttranslate('${player_name} passes'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(),
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("pass");
    }

    public function actSubmitDecision(int $decision) : void
    {
        $player_id = $this->getCurrentPlayerId();
        
        $this->playerActions[] = [
            'player_id' => $player_id,
            'action' => DECISIONS[$decision], 
            'timestamp' => time(),
        ];


        if (!empty($this->playerActions)) {
            $sql = "INSERT INTO player_actions (player_id, action, timestamp) VALUES ";
            $values = [];

            foreach ($this->playerActions as $entry) {
                $values[] = "('".$entry['player_id']."','".$entry['action']."','".$entry['timestamp']."')";
            }

            $sql .= implode(',', $values);
            $this->DbQuery($sql);

        $this->gamestate->setPlayerNonMultiactive($player_id, "");
        }
    }

    public function stHandleDecisions() : void
    {
        // print_r($this->playerActions);
        // // Step 1: Filter the array to get only "callBS" actions
        // $callBSActions = array_filter($this->playerActions, function ($entry) {
        //     return $entry['action'] === 'callBS';
        // });

        // // Step 2: Sort the filtered array by timestamp (ascending order, earliest first)
        // usort($callBSActions, function ($a, $b) {
        //     return $a['timestamp'] <=> $b['timestamp']; // Compare timestamps
        // });

        // // Step 3: Access the earliest "callBS" action (first item in the sorted array)
        // if (!empty($callBSActions)) {
        //     $earliestCallBS = $callBSActions[0]; // This will be the earliest "callBS" action
        //     // echo "Earliest callBS action: Player {$earliestCallBS['player_id']} at timestamp {$earliestCallBS['timestamp']}";
        //     $this->setGameStateValue("lastBSCaller", $earliestCallBS['player_id']);
        //     $this->gamestate->nextState('callBS');
        // } else {
        //     // echo "No 'callBS' action found.";
        //     $this->gamestate->nextState('nextPlayer');
        // }

        $sql = "SELECT player_id, action, timestamp 
        FROM player_actions 
        WHERE action = 'callBS' 
        ORDER BY timestamp ASC
        LIMIT 1";

        $dbRow = $this->getCollectionFromDb($sql);
        $firstBSCall = array_values($dbRow)[0];
        print_r($firstBSCall);
        if (!empty($firstBSCall)) {
            print_r("Earliest callBS action: Player {$firstBSCall['player_id']} at timestamp {$firstBSCall['timestamp']}");
            $this->setGameStateValue("lastBSCaller", $firstBSCall['player_id']);
            $this->gamestate->nextState('callBS');
        } else {
            // echo "No 'callBS' action found.";
            $this->gamestate->nextState('nextPlayer');
        }

        $sql = "DELETE FROM player_actions WHERE id NOT IN (
            SELECT id FROM (SELECT id FROM player_actions ORDER BY created_at DESC LIMIT 4) AS temp_table
        )";
        $this->DbQuery($sql);

    }

    // public function actCallBS(int $caller_id):
    // {
        
    // }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     *
     * @return array
     * @see ./states.inc.php
     */
    public function argPlayerTurn(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "playableCardsIds" => [1, 2],
            "turnCard" => VALUES_LABEL[$this->getGameStateValue("turnCard")/11],
        ];
    }

    public function argBSCall(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "numCardsPlayedLast" => $this->getGameStateValue("numCardsPlayedLast"),
            "caller" => $this->getPlayerNameById($this->getGameStateValue("lastBSCaller"))
        ];
    }

    public function argGivePile(): array
    {
        // Get some values from the current game situation from the database.

        
        return [
            "caller" => $this->getPlayerNameById($this->getGameStateValue("lastBSCaller")),
            "outcome" => OUTCOMES[$this->getGameStateValue("outcome")],
        ];
    }


    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        $this->setGameStateValue("turnCard", $this->updateTurnCard());

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);
        
        $this->activeNextPlayer();
        $this->playerActions = [];
        ///// Test if this is the end of the game
        if ($this->getUniqueValueFromDb("SELECT MIN(player_score) FROM player") > 0)
            $this->gamestate->nextState("endGame"); // Someone is dropped to 0 or below, trigger the end of the game !
        else $this->gamestate->nextState("nextPlayer"); // Otherwise, start a new hand
    

        // Go to another gamestate
        // Here, we would detect if the game is over, and in this case use "endGame" transition instead 
        // $this->gamestate->nextState("nextPlayer");
    }

    // public function stOfferBSCall() {
    //     // Activate all players for the 'call BS' decision
    //     $this->gamestate->setAllPlayersMultiactive();
    
    // }
        
    
    public function stCallBS(): void {
        // print_r( $this->cards->getCardsOnTop($this->getGameStateValue("numCardsPlayedLast"), "cardsontable"));
        $this->notifyAllPlayers('BSCalled', clienttranslate('${caller} called BS on ${player_name}'), array (
            'i18n' => array ('caller','player_name', 'cards' ),'caller' => $this->getPlayerNameById($this->getGameStateValue("lastBSCaller")),
            'player_name' => $this->getPlayerNameById($this->getGameStateValue("lastPlayer")),
            'cards' => $this->cards->getCardsOnTop($this->getGameStateValue("numCardsPlayedLast"), "cardsontable"),
            'player_id' => $this->getGameStateValue("lastPlayer")));

        $wasBS = false;
        $cards_called = $this->cards->getCardsOnTop($this->getGameStateValue("numCardsPlayedLast"), "cardsontable");
        // print_r($cards_called);
        $caller = ($this->getGameStateValue("lastBSCaller"));
        $needed_card = $this->getGameStateValue("turnCard")/11;

        foreach ($cards_called as $card) {
            if ($card['type_arg'] != $needed_card) {
                $wasBS = true;
                $this->setGameStateValue("receiver", $this->getGameStateValue("lastPlayer"));
                $this->setGameStateValue("outcome", 41);
            }else{
                $this->setGameStateValue("receiver", $caller);
                $this->setGameStateValue("outcome", 40);
            }
        }

        $this->gamestate->nextState("givePile");
        

        
    }

    public function stGivePile(): void{
        $cards_to_give = $this->cards->getCardsInLocation('cardsontable');

        $card_ids = array_column($cards_to_give, 'id');
        $this->cards->moveCards($card_ids, 'hand', $this->getReceiverOfPile());
        $caller = ($this->getGameStateValue("lastBSCaller"));
        $receiver = $this->getReceiverOfPile();
        // print_r("caller: $caller");
        // print_r("receiver: $receiver");

        
        if ( $receiver == $caller) {
            $this->notifyAllPlayers('BSHandled', clienttranslate('It wasn\'t BS!'), array (
                'i18n' => array ('player', 'cards_to_give' ),
                'player_id' => $caller, 'cards' => $cards_to_give));
    
            } else {
            $this->notifyAllPlayers('BSHandled', clienttranslate('It was BS!'), array (
                'i18n' => array ('player', 'cards_to_give' ),
                'player_id' => $receiver, 'cards' => $cards_to_give));
            }
    

        $this->gamestate->nextState("nextPlayer");
        }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas()
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        // Cards in player hand      
        $result['hand'] = $this->cards->getPlayerHand($current_player_id);
  
        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        // Num cards in opponent hands
        $idToNumCards = $this->cards->countCardsByLocationArgs('hand');
        $result['numCards'] = $idToNumCards;

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "ugobs";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.

        $this->setGameStateInitialValue( 'turnCard', 154 );
        $this->setGameStateInitialValue("numCardsPlayedLast", 50);
        $this->setGameStateInitialValue("lastBSCaller", 99);
        $this->setGameStateInitialValue("lastPlayer", 60);
        $this->setGameStateInitialValue("receiver", 72);
        $this->setGameStateInitialValue("outcome", 40);


        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }


        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // Create cards
        $cards = [];
        foreach (COLORS as $color_id => $color) // spade, heart, diamond, club
            for ($value = 2; $value <= 14; $value++) // 2, 3, 4, ... K, A
                $cards[] = ['type' => $color_id, 'type_arg' => $value, 'nbr' => 1];

        $this->cards->createCards($cards, 'deck');
        
        
        // Init global values with their initial values.


        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);

        // TODO: Setup the initial game situation here.

        // Count the number of cards to deal
        $player_list = $this->getObjectListFromDB("SELECT player_id id FROM player", true);
        $deal_amount = floor($this->cards->countCardInLocation('deck') / count($player_list));

        // Deal cards to each player
        // Create deck, shuffle it and give initial cards
        $this->cards->shuffle('deck');
        foreach ($player_list as $player_id) {
            $cards = $this->cards->pickCards($deal_amount, 'deck', $player_id);

            // Notify player about his cards
            $this->notifyPlayer($player_id, 'newHand', '', ['cards' => $cards]);
        }
    

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    protected function updateTurnCard(): int
    {
        $currCard = $this->getGameStateValue("turnCard") / 11;
        
        if ($currCard == 14) {
            $currCard = 2;
        } else {
            $currCard = $currCard + 1;
        }

        return $currCard * 11;
    }

    protected function getReceiverOfPile(): int
    {
        return $this->getGameStateValue("receiver");
    }
    function dbSetScore ($player_id, $count) {$this->DbQuery("UPDATE player SET player_score = '$count' WHERE player_id = '$player_id'");}

}
