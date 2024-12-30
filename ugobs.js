/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * UgoBS implementation : © Ugo Anyaegbunam anyaegbunamu@carleton.edu
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * ugobs.js
 *
 * UgoBS user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

 // Constants
 const PLACEMENTS = ['my', 'left', 'top', 'right']
 const DIRECTIONS = ['S', 'W', 'N', 'E']

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.ugobs", ebg.core.gamegui, {
        constructor: function(){
            console.log('ugobs constructor');
              
            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
            this.cardwidth = 72;
            this.cardheight = 96;


        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            console.log( "Starting game setup" );

            const orderedPlayers = this.getOrderedPlayers(gamedatas);


            document.getElementById('game_play_area').insertAdjacentHTML('beforeend', `
                <div id="game_board_wrap"> 
                    <div id="game_board">
                        <div id="bs_button">BS</div>
                        ${
                            orderedPlayers.map((player, index) => `
                            <div id ="${PLACEMENTS[index]}_hand_wrap" class="whiteblock ">
                                <b id="${PLACEMENTS[index]}_hand_label" style="color:#${player.color};">${PLACEMENTS[index] === 'my' ? "My hand" : player.name + "'s hand"}</b>
                                <div id="${PLACEMENTS[index]}_hand">
                                    <div class="playertablecard"></div>
                                </div>
                            </div>
                            `).join('')
                        }
                        <div id="card_pile" class="whiteblock">
                            <div class="playertablename">Card Pile</div>
                            <div class="playertablecard" id="playertablecard_pile"></div>
                        </div>

                        <div id="reveal_card_pile">
                            <div class="playertablecard" id="reveal_playertablecard_pile"></div>
                        </div>
                    </div>
                </div>
            `);

            // document.getElementById('bs_button').addEventListener('click', callBS);

            // Player hand
            this.playerHand = new ebg.stock();
            this.playerHand.create(this, $('my_hand'), this.cardwidth, this.cardheight);
            // this.playerHand.extraClasses = 'stock_card_border card_' + this.getGameUserPreference(100);
            this.playerHand.centerItems = true;
            this.playerHand.image_items_per_row = 13;
            this.playerHand.apparenceBorderWidth = '2px'; // Change border width when selected
            this.playerHand.setSelectionMode(2); // Select only a single card
            this.playerHand.horizontal_overlap = 28;
            this.playerHand.item_margin = 0;

            // dojo.connect(this.playerHand, 'onChangeSelection', this, 'onHandCardSelect');
            dojo.connect( this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged' );

            // Create cards types:
            for (let color = 1; color <= 4; color++)
                for (let value = 2; value <= 14; value++) {
                    // Build card type id
                    const card_type_id = this.getCardUniqueId(color, value);
                    // Change card image style according to the preference option
                    this.playerHand.addItemType(card_type_id, card_type_id, g_gamethemeurl + 'img/cards1.jpg', card_type_id);
                }
            
            // Cards in player's hand
            for (let i in gamedatas.hand) {
                const card = gamedatas.hand[i];
                const color = card.type;
                const value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
            
            // Cards played on table
            for (let i in gamedatas.cardsontable) {
                const card = gamedatas.cardsontable[i];
                const color = card.type;
                const value = card.type_arg;
                const player_id = card.location_arg;
                this.addTableCard(value, color, player_id, player_id, "back");
            }

            
            
            // TODO: Set up your game interface here, according to "gamedatas"
            
 
            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },

       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName, args );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */
           
           
            case 'dummy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName, args );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                 case 'playerTurn':    
                    const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

                    // Add test action buttons in the action status bar, simulating a card click:
                    playableCardsIds.forEach(
                        cardId => this.addActionButton(`actPlayCard${cardId}-btn`, _('Play card with id ${card_id}').replace('${card_id}', cardId), () => this.onCardClick(cardId))
                    ); 

                    this.addActionButton('actPass-btn', _('Pass'), () => this.bgaPerformAction("actPass"), null, null, 'gray'); 
                    break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */
        getOrderedPlayers(gamedatas) {
            const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
            const playerIndex = players.findIndex(player => Number(player.id) === Number(this.player_id));
            const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
            return orderedPlayers;
        },

        playCardOnTable : function(player_id, color, value, card_id) {
            // player_id => direction
            this.addTableCard(value, color, player_id, card_id, "back");

            if (player_id != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + card_id + '_' + player_id, 'overall_player_board_' + player_id);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove
                // corresponding item

                if ($('my_hand_item_' + card_id)) {
                    this.placeOnObject('cardontable_' + card_id + '_' + player_id , 'my_hand_item_' + card_id);
                    this.playerHand.removeFromStockById(card_id);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + card_id + '_' + player_id, 'playertablecard_pile').play();
        },

        addTableCard(value, color, card_player_id, card_id, side) {
            const x = value - 2;
            const y = color - 1;
            if (side == 'back') {
                document.getElementById('playertablecard_pile').insertAdjacentHTML('beforeend', `
                    <div class="card cardontable" id="cardontable_${card_id}_${card_player_id}" style="background-position:-1400% -00%"></div>
                `);
            } else {
            document.getElementById('playertablecard_pile').insertAdjacentHTML('beforeend', `
                <div class="card cardontable" id="revealed_cardontable_${card_id}_${card_player_id}" style="background-position:-${x}00% -${y}00%"></div>
            `);
            }
        },

        revealCardOnTable : function(player_id, color, value, card_id) {
            this.addTableCard(value, color, player_id, player_id, "front");

            // Move card from pile to side and reveal

            if ($('cardontable_' + card_id + '_' + player_id)) {
                this.placeOnObject('revealed_cardontable_' + card_id + '_' + player_id, 'cardontable_' + card_id + '_' + player_id);
            } 


            this.slideToObject('revealed_cardontable_' + card_id + '_' + player_id, 'revealed_playertablecard_pile').play();
        },
    
        // Get card unique identifier based on its color and value
        getCardUniqueId: function (color, value) {
            return (color - 1) * 13 + (value - 2);
        },


        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */
        
        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'actPlayCard';
                if (this.checkAction(action, true)) {
                    // Can play a card
                    var card_id = items[0].id;                    
                    this.bgaPerformAction(action, {
                        card_id : card_id,
                    });

                    this.playerHand.unselectAll();
                } else if (this.checkAction('actGiveCards')) {
                    // Can give cards => let the player select some cards
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        callBS: function(player_id) {
            

        },

        // Example:
        
        onCardClick: function( card_id )
        {
            console.log( 'onCardClick', card_id );

            this.bgaPerformAction("actPlayCard", { 
                card_id,
            }).then(() =>  {                
                // What to do after the server call if it succeeded
                // (most of the time, nothing, as the game will react to notifs / change of state instead)
            });        
        },    

        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your ugobs.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            
            const notifs = [
                ['newHand', 1],
                ['playCard', 100],
            ];
    
            notifs.forEach((notif) => {
                dojo.subscribe(notif[0], this, `notif_${notif[0]}`);
                this.notifqueue.setSynchronous(notif[0], notif[1]);
            });

        },

        notif_newHand : function(notif) {
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

            for ( var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);
        },        
        // TODO: from this point and below, you can write your game notifications handling methods
        
        /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */
   });             
});
