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

/**
 * Service to handle User recommendations
 *
 * @author dfanchon
 * @category Rubedo
 * @package Rubedo
 */
class UserRecommendations extends AbstractCollection
{
    public function __construct()
    {
        $this->_collectionName = 'UserRecommendations';
        parent::__construct();
    }

    public function getRec(){
        $pipeline=array();
        $pipeline[]=array(
            '$match'=>array(
                'userIP'=> $_SERVER['REMOTE_ADDR']
            )
        );
        $pipeline[]=array(
            '$unwind'=>'$reco'
        );
        $pipeline[]=array(
            '$project'=>array(
            	'_id' => 0,
            	'id' => '$reco.cid',
            	'score' => '$reco.score'
            )
        );
        $pipeline[]=array(
        		'$sort'=>array(
        				'score'=>-1
        		)
        );
        $pipeline[]=array(
            '$limit'=> 20
        );        
        
        $response=$this->_dataService->aggregate($pipeline);
        
        if ($response['ok']){
            return array(
                "data"=>$response['result'],
                "total"=>count($response['result']),
                "success"=>true
            );
        } else {
            return array(
                "msg"=>$response['errmsg'],
                "success"=>false
            );
        }

    }
}
