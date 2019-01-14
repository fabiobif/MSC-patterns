<?php
$start = microtime(true);

//variables
$suport         = 0.02;
$totalTransact  = 2493;
$candidate1     = array('NPV','VBG','NN','NPO','VB','JJ','NNS','VBZ','NPSP','RB','VBN','NPG','W','VBD','JJS','VBP','JJR','RBR','PRP','RBS','IN','Z','CC');
$candidate2     = array('time','person','state',' body',' change',' contact','stative','all','artifact','action','relation','communication','motion',
                'social','consumption','possession','creation','cognition','object','shape','location','attribute','group','phenomenon',
                'quantity', 'plant','perception','feeling','competition','food','event','organization','animal','emotion','process','substance',
                'motive','pert','weather','ppl', 'other', '-');

$outPut = explode("\n", file_get_contents('msc-microposts2016test.txt'));


$cms = cmsCandidate($candidate1, $candidate2, $outPut, $suport, $totalTransact);

cmsCandidateMining($cms, $cmsInteract = $cms, $totalTransact, $suport);

//Para determinar padrão de tamanho 1, combinando tag e sentido em uma expressão regular.
//Retorna todas as combinações e faz um parser conforme um valor de suporte.

function cmsCandidate($candidate1, $candidate2, $outPut, $suport, $totalTransact){
   
    foreach($candidate1 as $cand1){
            
        foreach($candidate2 as $cand2){
                        
            $pattern = '/'.$cand1.'\s.*'.$cand2.'/';  
            
            //Retorna todos os pares chave => valor onde o valor tem match com pattern
            $cmsFilter = preg_grep($pattern, $outPut);

            if (!empty($cmsFilter) && (count($cmsFilter)/$totalTransact) >= $suport) {
                
                $cmsCandidateMining[$cand1.','.$cand2] = cmsParse($cmsFilter);
            }
        }
    }

    putCmsMining($cmsCandidateMining);

    return $cmsCandidateMining;
}

function cmsParse($cmsFilter){

    foreach($cmsFilter as $str){

        $str = explode("\t", $str);

        $cms[$str[0]] = $str[2];
    }

    return $cms;
}

//faz a combinação entre padrão de tamanho 1 x tamanho 1, padrão de tamanho 1 x tamanho 2... de maneira recursiva 
function cmsCandidateMining($cms, $cmsInteract, $totalTransact, $suport){

    foreach($cms as $key1 => $idTweet1){
        
        foreach($cmsInteract as $key2 => $idTweet2){
            
            if($key1 != $key2){
                
                $cmsIntersect = cmsIntersect($key1, $idTweet1, $key2, $idTweet2, $totalTransact, $suport);

                // if (!empty($cmsIntersect) && (count($cmsIntersect)/$totalTransact) >= $suport)
                if (!empty($cmsIntersect)) $cmsCandidateMining[$key1.' '.$key2] = $cmsIntersect;           
                
            }
        }
    }
        
    if(!empty($cmsCandidateMining)){

        putCmsMining($cmsCandidateMining);     
        
        cmsCandidateMining($cms, $cmsCandidateMining, $totalTransact, $suport);    
    }

}

// cmsIntersect: contém a intersecção entre os padrões preservando o par 'id cms' => 'id tweet', com alinhamento à esquerda. 
// Verifica o suporte do resultado.
// cmsIntersectFilter: filtra os valores de mesmo 'id tweet' entre idTweet1 e idTweet2 (array usado para interar os padrões), e então
// verifica a ordem dos componentes, 'id cms'.
function cmsIntersect($key1, $idTweet1, $key2, $idTweet2, $totalTransact, $suport){

    $cmsPlus = array();

    $cmsIntersect = array_intersect($idTweet1, $idTweet2);  
    
    if (!empty($cmsIntersect) && (count($cmsIntersect)/$totalTransact) >= $suport){
    //echo "o valor de $key1 $key2 tem: ". count($cmsIntersect)." <br >";

        foreach ($cmsIntersect as $idCms => $idTweet){
                        
            $cmsIntersectFilter = array_filter($idTweet2, function($value, $key) use ($idCms, $idTweet) { return $value == $idTweet && $key > $idCms; }, ARRAY_FILTER_USE_BOTH);
                                        
            if (!empty($cmsIntersectFilter)){
                //echo "$key1 e $key2 o id $idCms e $idTweet: <br >";

                foreach($cmsIntersectFilter as $key => $value){ $cmsPlus[$idCms.'-'.$key] =  $value; }
            }                 
        }
    }

    return $cmsPlus;
}

// apenas para guardar os padrões minerados em dois arquivos, um com os padrões sem um sentido definido (cmsInduciton-microposts2016test-pattern),
// e o outro com os padrões candidatos (cms-microposts2016test-pattern).

function putCmsMining($cmsCandidateMining){
    global $start;

    // filtra os padrões com sentido indefinido, ex.: NPV,-
    $cmsCandidateInduction = array_filter($cmsCandidateMining, function($key) { return substr_count($key, '-') >= 1; }, ARRAY_FILTER_USE_KEY);
    
    //tamanho do padrão
    $patternLen = substr_count(key($cmsCandidateInduction), ',');
    
    //quantidade de padrões
    $qtd = count($cmsCandidateInduction);
     
    // guardar o conteúdo anterior 
    file_put_contents("weakly-disambiguated-msc$patternLen-$qtd.txt", json_encode($cmsCandidateInduction));
       
    // filtra os padrões com sentido
    $cmsCandidatePattern = array_filter($cmsCandidateMining, function($key) { return substr_count($key, '-') == 0; }, ARRAY_FILTER_USE_KEY);
    
    $patternLen = substr_count(key($cmsCandidatePattern), ',');
    
    //quantidade de tweets com o padrão
    $qtd = count($cmsCandidatePattern);

    file_put_contents("strongly-disambiguated-msc$patternLen-$qtd.txt", json_encode($cmsCandidatePattern));

    $execTime = microtime(true) - $start;
    echo "strongly-disambiguated-msc$patternLen-$qtd.txt, tempo de execusão ". number_format($execTime, 6) ." s<br >";
    $start = microtime(true); 
    
}

?>