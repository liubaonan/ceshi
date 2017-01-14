<?php
namespace M_home\Model;
use M_home\Model\Member_ent_brandinfoMod;
use Think\Model;
class Member_ent_brandinfoModel extends Model{
    protected $tableName = '';                //如果没有在配置文件中设置表前缀，就应该设置为protected $trueTableName = 'crm28_client'; 

    
    public function getPublic(){
        $redislbn = A('Common/Comment');                       //实例化Common模块下的Comment控制器
        $redislbn->getRedis();  
        $redislbn->getRedisNew();                              //调取redis方法
        $a = $redislbn->redisNew->keys('*');
        return $a;
        
    }
	/**
		*标签使用的方法
	**/
    function getProjectList($tag,$content){
		$tag=json_decode($tag,true);///所有的参数
		$redislbn  = A('Common/Comment');
        $redislbn->getRedis();
		$where=' a.status=1 ';
		if(isset($tag['mounth'])){
			$tempidarr=$this->setPaihang($tag['cid'],$tag['mounth']);
		}
		///行业id不限制大行业和小行业
		if(isset($tag['cid'])){
			$where.=' and (a.industry ='.$tag['cid'].' or a.subindustry='.$tag['cid'].')';
		}
		///显示的数量
		$row=10;
		if(isset($tag['row'])){
			$row=intval($tag['row']);
		}
		///排行榜的项目新闻id
		if(!isset($tempidarr)){			
			$tempiarr=$this->query("select b.memberid from ls_member_ent_brandinfo as b left join ls_member_ent_info as a on a.memberid=b.memberid where $where order by orderid desc,cindex desc limit ".$row);
			$tempidarr=array();
			foreach($tempiarr as $one){
				$tempidarr[]=$one['memberid'];
			}
		}
		$temparr=$redislbn->redis->hmget('ls_member_ent_brandinfo',$tempidarr);
		$i=1;
		foreach($temparr as &$one){
			$one=json_decode($one,true);
			$one['title']=$one['brandname'];
			$one['oid']=$i;
			$i++;
		}
		return $temparr;
	}
	/**
		*标签使用的方法
	**/
	function setPaihang($industry,$mounth=''){
		$news_phb=M('news_phb');
		$mounth=empty($mounth)?date('Y-m'):$mounth;
		$temparr=$news_phb->where('industry='.$industry.' and yandm=\''.$mounth.'\'')->order('id desc')->limit(1)->find();
		if(!$temparr){///没有读取
			$tempiarr=$news_phb->query("select b.memberid from ls_member_ent_brandinfo as b left join ls_member_ent_info as a on a.memberid=b.memberid where b.cindex>0 and a.industry=$industry or subindustry=$industry order by orderid desc,cindex desc limit 10"); 
			//echo $news_phb->getLastSQL();die;
			$allid=array();
			foreach($tempiarr as $one){
				$allid[]=$one['memberid'];
			}
			$allidstr=join(',',$allid);
			$news_phb->data(array('industry'=>$industry,'yandm'=>$mounth,'allxm'=>$allidstr))->add();	
			echo $news_phb->getLastSQL();die;
		}else{
			//print_r($temparr);die;
			$allid=explode(',',$temparr['allxm']);
		}
		return $allid;
		
	}
    //品牌新闻重写
    function ppxwcx($memberid,$start,$pgnum){
        $redislbn  = A('Common/Comment');
        $redislbn->getRedisNew();
        if($redislbn->redisNew->exists($memberid)){
            $newsNum = json_decode($redislbn->redisNew->get($memberid));
            $ppxwArray1 = $redislbn->redisNew->getMultiple(array_slice($newsNum,$start,$pgnum));
            $ppxwArray = array();
            foreach($ppxwArray1 as $one){
                $ppxwArray[] = json_decode($one,true);
            }
        }else{
            $newsModel = new Model();
            $ppxwArrayID1=$newsModel->query("select newsid from ls_news_arc where  member_id=" . $memberid . " and iscreatehtml in (1,2) and flag in(1,2)   order by newsid desc");
            $ppxwArrayID = array();
            foreach($ppxwArrayID1 as $one){
                $ppxwArrayID[] = 'news_'.$one['newsid'];
            }
            $redislbn->redisNew->setex($memberid,86400,json_encode($ppxwArrayID));
            $ppxwArray1 = $redislbn->redisNew->getMultiple(array_slice($ppxwArrayID,$start,$pgnum));
            $ppxwArray = array();
            foreach($ppxwArray1 as $one){
                $ppxwArray[] = json_decode($one,true);
            }
            $newsArc = implode('',$ppxwArray);
            if(empty($newsArc)){
                $arcArray = $newsModel->query("select * from ls_news_arc where  member_id=" . $memberid . " and iscreatehtml in (1,2) and flag in(1,2) order by newsid desc");
                foreach($arcArray as $one){
                    $redislbn->redisNew->setex('news_'.$one['newsid'],86400,json_encode($one));
                }
                $ppxwArray1 = $redislbn->redisNew->getMultiple(array_slice($ppxwArrayID,$start,$pgnum));
                $ppxwArray = array();
                foreach($ppxwArray1 as $one){
                    $ppxwArray[] = json_decode($one,true);
                }
            }
        }
        return $ppxwArray;
    }
    

    //根据小行业取出项目精品推荐
    function jptj($industry=0,$length=10){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
        if($redislbn->redisNew->exists('jptjlbn_'.$industry)){
            $jptjArray = json_decode($redislbn->redisNew->get('jptjlbn_'.$industry),true);
        }else{
            $model = new Model();
            $jptjArray = $model->query("select a.memberid,a.cindex,a.brandname,a.projectbrief,a.joinlinemin,a.joinlinemax,b.memberid,b.logo,b.isupload,b.industry,b.subindustry
                from ls_member_ent_brandinfo as a,ls_member_ent_info as b where
                a.memberid = b.memberid and b.industry=$industry order by a.cindex desc limit 0,50");
            $redislbn->redisNew->setex('jptjlbn_'.$industry,86400,json_encode($jptjArray));
        }
        return $jptjArray;
    }
    
    //新闻页面相关阅读
    function tjyd($string,$newsid,$catalog_id){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
       // $redislbn->redisNew->del('newstjyd_'.$newsid);
        if(!$redislbn->redisNew->exists('newstjyd_'.$newsid)){
            $model = new Model('news_arc');
            if(!empty($string)){
                $string=str_replace('，', ',', $string);
                $string=str_replace(' ', ',', $string);
                $temparr=explode(',', $string);
                $string=array();
                $where['keywords'] =array('like','%'.$temparr[0].'%');
                $where['iscreatehtml'] = 1;
                $where['flag'] = array('neq',0);
                $arcArray = $model->field('newsid')->where($where)->order('newsid desc')->limit(8)->select();
              //  var_dump($arcArray);die;
                foreach($arcArray as $one){
                    $array[] = $one['newsid'];
                }
                shuffle($array);
            }
            if(count($array)<8){
                if(!empty($temparr[1])){
                    $where2['keywords'] =array('like','%'.$temparr[1].'%');
                    $where2['iscreatehtml'] = 1;
                    $where2['flag'] = array('neq',0);
                    $arcArray = $model->field('newsid')->where($where2)->order('newsid desc')->limit(8)->select();
                    foreach($arcArray as $one){
                        array_push($array,$one['newsid']);
                        shuffle($array);
                    }
                }
            if(count($array)<8){
                    $where3['catalog_id'] = $catalog_id;
                    $where3['iscreatehtml'] = 1;
                    $where3['flag'] = array('neq',0);
                    $arcArray = $model->field('newsid')->where($where3)->order('newsid desc')->limit(8)->select();
                    if(empty($array)){$array = array();}
                    foreach($arcArray as $one){
                        array_push($array,$one['newsid']); 
                        shuffle($array);
                    }
                }
            }
            $redislbn->redisNew->setex('newstjyd_'.$newsid,86400,json_encode($array));
            $arrayNews = array();
            foreach($array as $one){
                $arrayNews[] = 'news_'.$one;
            }
            $tjydArray2 = $redislbn->redisNew->getMultiple($arrayNews);
            $tjydArray = array();
            foreach($tjydArray2 as $one){
                $tjydArray[] = json_decode($one,true);
            }
           // var_Dump($array);die;
        }else{
            $array = json_decode($redislbn->redisNew->get('newstjyd_'.$newsid),true);
            $arrayNews = array();
            foreach($array as $one){
                $arrayNews[] = 'news_'.$one;
            }
            $tjydArray2 = $redislbn->redisNew->getMultiple($arrayNews);
            $tjydArray = array();
            foreach($tjydArray2 as $one){
                $tjydArray[] = json_decode($one,true);
            }
        }
        return $tjydArray;
        
    }
    
    //news碎片
    function news_name($newname,$catalog_id,$start,$length,$source,$member_id){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
        $redislbn->getRedis(); 
        //$redislbn->redisNew->del($newname.'_'.$catalog_id); 
        if($redislbn->redisNew->exists($newname.'_'.$catalog_id)){
            $newsid = json_decode($redislbn->redisNew->get($newname.'_'.$catalog_id),true);
            $news_name1 = $redislbn->redisNew->getMultiple($newsid);
            $news_name = array();
            foreach($news_name1 as $two){
                $news_name[] = json_decode($two,true);
            }
            shuffle($news_name);
        }else{
            $model = new Model();
            $newsModel1 = $model->query("select newsid,member_id from ls_news_arc where catalog_id=$catalog_id and flag!=0 and iscreatehtml!=0
            order by ctime desc limit $start,$length");
            $newsModel = array();
            foreach($newsModel1 as $one){
                if($redislbn->redis->hexists('ls_member_ent_brandinfo', $one['member_id'])===false && $one['member_id'] !=='0'){
                }else{
                    $newsModel[] = 'news_'.$one['newsid'];
                }
            }
            $redislbn->redisNew->setex($newname.'_'.$catalog_id,86400,json_encode($newsModel));
            $news_name1 = $redislbn->redisNew->getMultiple($newsModel);
            $news_name = array();
            foreach($news_name1 as $two){
                $news_name[] = json_decode($two,true);
            }
            shuffle($news_name);
        }
        
        return $news_name;
    }
    
    //调取资源系统广告图
    function news_source($newname,$catalog_id,$length,$source){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
        $redislbn->redisNew->del($newname.'_'.$catalog_id);
        if($redislbn->redisNew->exists($newname.'_'.$catalog_id)){
            $news_name = json_decode($redislbn->redisNew->get($newname.'_'.$catalog_id),true);
        }else{
            $source = str_replace ( '{', '', $source );
            $source = str_replace ( '}', '', $source );
            $temparrone = explode ( ':', $source );
            $size = $temparrone[1];
            $model = new Model();
            $sourceArray = $model->query("SELECT a.*,b.* from `ls_source_type` as a,`ls_source_content` as b WHERE source_size='$size' and a.id=b.typeid and b.source_project=1 order by b.id desc limit 0,$length");
            $newsModel = array();
            foreach($sourceArray as $one){
                $newsModel[] = 'news_'.$one['newsid'];
            }
            $news_name1 = $redislbn->redisNew->getMultiple($newsModel);
            $news_name = array();
            foreach($news_name1 as $two){
                $news_name[] = json_decode($two,true);
            }
            $news_name2 = array();
            foreach($news_name as $key=>&$three){
                $three['source_content'] = $sourceArray[$key]['source_content'];
            }
            $redislbn->redisNew->setex($newname.'_'.$catalog_id,86400,json_encode($news_name));
        }
        return $news_name;
    }
    

    //调取广告
    function newsad($newsadname,$channelID,$start,$length){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
        //$redislbn->redisNew->del($newsadname.'_'.$channelID);
        if($redislbn->redisNew->exists($newsadname.'_'.$channelID)){
            $newsModel = json_decode($redislbn->redisNew->get($newsadname.'_'.$channelID),true);
        }else{
            $model = new Model();
            $newsModel = $model->query("SELECT * FROM `ls_ad_banner` WHERE channelID=$channelID order by id desc limit $start,$length");
            
            $redislbn->redisNew->setex($newsadname.'_'.$channelID,86400,json_encode($newsModel));
        }
        return $newsModel;
    }
    

    //取出加盟聚焦
    function jmjj($memberid){
        $redislbn = A('Common/Comment');
        $redislbn->getRedisNew();
        if($redislbn->redisNew->exists('jmjjlbn_'.$memberid)){
            $jmjjArray = json_decode($redislbn->redisNew->get('jmjjlbn_'.$memberid),true);
        }else{
            $newsarcModel = new Model('news_arc');
            $where2['member_id'] = $memberid;
            $jmjjArray = $newsarcModel->where($where2)->order('ctime desc')->limit(10)->select();
            $redislbn->redisNew->setex('jmjjlbn_'.$memberid,86400,json_encode($jmjjArray));
        }
        return $jmjjArray;
    }
    

    //取出排行榜
    function phb($industry=0,$length=10){
        $top = A('Common/Comment');
        $top->getRedisNew();
       // var_dump($top->redisNew->del('industryTop_'.$industry));
        if($top->redisNew->exists('industryTop_'.$industry)){
            $phb = $top->redisNew->get('industryTop_'.$industry);
        }else{
            $top->industryTop($industry,$length);
            $phb = $top->redisNew->get('industryTop_'.$industry);
        }
        return json_decode($phb,true);
    }
     
    //news页面下相关阅读出的tag处理
    public function strtotag($newsid){
        $tagindexModel = new Model('seo_taglist');
        $where['aid'] = $newsid;
        $tagindexArray = $tagindexModel->field('tid,tag')->where($where)->select();
        foreach ($tagindexArray as $one){
            if(!empty($one)){
                $tagindexArray2[] = $one;
            }
        }
		return $tagindexArray2;
    }
}
?>