<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Collection;

use Rubedo\Mongo\DataAccess;

/**
 * Service to handle content view logging
 *
 * @author adobre
 * @category Rubedo
 * @package Rubedo
 */
class ContentViewLog extends AbstractCollection
{

    public function __construct()
    {
        $this->_collectionName = 'ContentViewLog';
        parent::__construct();
    }

    public function init () {
    	
    }
    
    public function log ($contentId, $locale, $ip, $timestamp){
        $this->_dataService->directCreate(array(
            "contentId"=>$contentId,
            "viewedLocale"=>$locale,
            "userIP"=>$ip,
            "timestamp"=>$timestamp
        ));
    }
    
    public function setItemRecommendations() {
    	
    	$mapCode =	"
	    	function() {
				var content = this;
				if (content.live) {
					if (content.live.taxonomy){
						for (var vocabulary in content.live.taxonomy) {	
							if ((content.live.taxonomy.hasOwnProperty(vocabulary)) 
    								&& (typeof content.live.taxonomy[vocabulary] != 'string') 
    									&& (content.live.taxonomy[vocabulary])) {
								content.live.taxonomy[vocabulary].forEach (function(term) {
    								if (term>'') {
										value = {};
										value[content._id.valueOf()]=1;
										emit(term, value);	
    								}
								});
							}
						}
					}
				}
			};";
    	
		$reduceCode = "
			function(key, values) {
				var result = {};
				values.forEach( function(element) {
					key = Object.keys(element)[0];
					result[key] = 1;
				});
				return result;
			}";
		
		$params = array(
				"mapreduce" => "Contents", // collection
				"map" => new \MongoCode($mapCode), // map
				"reduce" => new \MongoCode($reduceCode), // reduce
				"out" => array("replace" => "tmpRecommendations") // out
		);
		
		$response = $this->_dataService->command($params);		

		$mapCode =	"
			function() {
				var term = this; 
				var ids = Object.keys(term.value); 
				if (ids.length>1) {
					for (var i=0; i < ids.length; i++) {
						for (var j=i+1; j < ids.length; j++) {
							value = {}; 
							value[ids[j]]=1; 
							emit(ids[i], value);
    					}
    				}
    			}
    		}";

		$reduceCode = "
			function(key, values) {
				var result = {};
				values.forEach( function(element) {
					key = Object.keys(element)[0];
					if (result[key]) {
						result[key]=result[key]+1;
					} else {
						result[key]=1;
					}
				});
				return result;
			}";

		$params = array(
				"mapreduce" => "tmpRecommendations", // collection
				"map" => new \MongoCode($mapCode), // map
				"reduce" => new \MongoCode($reduceCode), // reduce
				"out" => array("replace" => "ItemRecommendations") // out
		);
		
		$response = $this->_dataService->command($params);
		
		return $response;
		
    }
    	
    public function viewLogPop() {
    		
		$code = "
			db.tmpRecommendations.drop();
			db.ContentViewLog.find().snapshot().forEach(function(foo) {
				var v = db.ItemRecommendations.findOne({_id:foo.contentId});
				if (v) {
					for (var content in v.value) {
						db.UserRecommendations.update(
							{ userIP: foo.userIP },
							{ \$addToSet : {reco: {cid: content, score:  v.value[content]}}},
							{ upsert: true }
						);					
						db.UserRecommendations.update(
							{ userIP: foo.userIP, reco: { \$elemMatch: { cid: content } } },
							{ \$inc: {'reco.$.score' : v.value[content]}}
						);
						var action = {};
						action[foo.contentId] = '';
					}
					db.ContentViewLog.remove(foo);
					db.UserRecommendations.update({ userIP: foo.userIP },{\$pull: { reco: {'cid': foo.ContentId}}});
				}
			});";

		$response = $this->_dataService->execute($code);
    		
    	return $response;
    }

}
