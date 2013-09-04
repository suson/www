/**
     * 
     * @param type $currentwhere  root_0,root_1...userno_oa号，id_id号
     * @param type $userno
     * @param type $enterpriseno
     * @param type $opt
     * @return type
     */
    public static function get_current_search_where($currentwhere, $userno, $enterpriseno, $opt) {
        $c_type = 'root';
        $c_value = 0;
        $currentwhere_arr = explode('_', $currentwhere);
        $c_type = $currentwhere_arr[0];
        $c_value = array_pop($currentwhere_arr);
        switch ($c_type) {
            case 'root' :
                if (2 == $c_value) { //个人
                    $opt['where'] .= ' and type=1 and userno=?';
                    $opt['param'][] = $userno;
                } elseif (1 == $c_value) { //公司
                    if ((self::issuperadmin($userno, $enterpriseno) || self::isadmin($userno, $enterpriseno))) {
                        $opt['where'] .= ' and type=0';
                    } else {
                        $opt['where'] .= ' and (type=0 and (roles like ? or roles like ? or roles like ?) and !find_in_set(?,directoryproperty))';
                        $opt['param'][] = '%a:0|r|%';
                        $opt['param'][] = '%a:0|r|%';
                        $opt['param'][] = '%u:' . $userno . '|r|%';
                        $opt['param'][] = 2; //隐藏属性
                    }
                } elseif (3 == $c_value) { //我的共享
                    $opt['where'] .= ' and type=1 and isshare=1 and userno=?';
                    $opt['param'][] = $userno;
                } elseif (4 == $c_value) { //同事共享
                    $ids_arr = self::currentuser_can_see_colleague_documents($userno, $enterpriseno, true);
                    $opt['where'] .= ' and type=1 and isshare=1 and userno!=? and find_in_set(documentid,?)';
                    $opt['param'][] = $userno;
                    $opt['param'][] = $ids_arr;
                } else {
                    $ids_arr = self::currentuser_can_see_colleague_documents($userno, $enterpriseno, true);
                    if ((self::issuperadmin($userno, $enterpriseno) || self::isadmin($userno, $enterpriseno))) {
                        $opt['where'] .= ' and ((type=1 and userno=?) 
                or (type=0) 
                or (type=1 and isshare=1 and userno=?)
                or (type=1 and isshare=1 and userno!=? and find_in_set(documentid,?))
                )';
                        $opt['param'][] = $userno;
                        $opt['param'][] = $userno;
                        $opt['param'][] = $userno;
                        $opt['param'][] = $ids_arr;
                    } else {
                        $opt['where'] .= ' and ((type=1 and userno=?) 
                        or (type=0 and (roles like ? or roles like ? or roles like ?) and !find_in_set(?,directoryproperty))
                        or (type=1 and isshare=1 and userno=?)
                        or (type=1 and isshare=1 and userno!=? and find_in_set(documentid,?))
)';
                        $opt['param'][] = $userno;
                        $opt['param'][] = '%a:0|r|%';
                        $opt['param'][] = '%a:0|r|%';
                        $opt['param'][] = '%u:' . $userno . '|r|%';
                        $opt['param'][] = 2; //隐藏属性
                        $opt['param'][] = $userno;
                        $opt['param'][] = $userno;
                        $opt['param'][] = $ids_arr;
                    }
                }
                break;
            case 'userno' :  //某一个同事共享
                $ids_arr = self::currentuser_can_see_colleague_documents($userno, $enterpriseno, true);
                $opt['where'] .= ' and type=? and isshare=? and userno=? and find_in_set(documentid,?)';
                $opt['param'][] = 1;
                $opt['param'][] = 1;
                $opt['param'][] = $c_value;
                $opt['param'][] = $ids_arr;
                break;
            case 'id' : //某一个目录
                $opt['where'] .= ' and ((find_in_set(?,fullpath) and type=1) 
                    or (find_in_set(?,fullpath) and type=0 and (roles like ? or roles like ? or roles like ?) and !find_in_set(2,directoryproperty))
                    )';
                $opt['param'][] = $c_value;
                $opt['param'][] = $c_value;
                $opt['param'][] = '%a:0|r|%';
                $opt['param'][] = '%a:0|r|%';
                $opt['param'][] = '%u:' . $userno . '|r|%';
                break;
        }
        return $opt;
    }

    // 返回搜索条件

    public static function get_search_where($userno, $enterpriseno, $params) {
        Doo::loadClass('Enum');
        $keyword = isset($params['filename']) ? urldecode(trim($params['filename'])) : '';
        $filewhere = isset($params['filewhere']) ? $params['filewhere'] : 'b'; //b指所有位置
        $filetype = isset($params['filetype']) ? $params['filetype'] : 'all'; //all指所有类型
        $fileupdatetime = isset($params['fileupdatetime']) ? $params['fileupdatetime'] : 'all'; //all指所有时间
        $currentwhere = isset($params['currentwhere']) ? $params['currentwhere'] : 'root_0';
        $SDate = isset($params['SDate']) ? $params['SDate'] : date('Y-m-d');
        $EDate = isset($params['EDate']) ? $params['EDate'] : date('Y-m-d');
        $opt['where'] = 'enterpriseno =? and status =? and parentid>0';
        $opt['param'] = array($enterpriseno, Enum::getStatusType('Normal'));
        $opt['asc'] = 'isfile';
        if ($keyword != '') {
            $opt['where'] .= ' and (directoryname like ? or filename like ?)';
            $opt['param'][] = '%' . $keyword . '%';
            $opt['param'][] = '%' . $keyword . '%';
        }
        //搜索位置start
        switch ($filewhere) {
            case 'a' : //当前位置
                $opt = self::get_current_search_where($currentwhere, $userno, $enterpriseno, $opt);
                break;
            case 'b' : //所有位置
                $ids_arr = self::currentuser_can_see_colleague_documents($userno, $enterpriseno, true);
                if ((self::issuperadmin($userno, $enterpriseno) || self::isadmin($userno, $enterpriseno))) {
                    //每行对应个人，公司，共享，同事共享
                    $opt['where'] .= ' and ((type=1 and userno=?) 
                or (type=0) 
                or (type=1 and isshare=1 and userno=?)
                or (type=1 and isshare=1 and userno!=? and find_in_set(documentid,?))
                )';
                    $opt['param'][] = $userno;
                    $opt['param'][] = $userno;
                    $opt['param'][] = $userno;
                    $opt['param'][] = $ids_arr;
                } else {
                    //每行对应个人，公司，共享，同事共享
                    $opt['where'] .= ' and ((type=1 and userno=?) 
                        or (type=0 and (roles like ? or roles like ? or roles like ?) and !find_in_set(?,directoryproperty))
                        or (type=1 and isshare=1 and userno=?)
                        or (type=1 and isshare=1 and userno!=? and find_in_set(documentid,?))
)';
                    $opt['param'][] = $userno;
                    $opt['param'][] = '%a:0|r|%';
                    $opt['param'][] = '%a:0|r|%';
                    $opt['param'][] = '%u:' . $userno . '|r|%';
                    $opt['param'][] = 2; //隐藏属性
                    $opt['param'][] = $userno;
                    $opt['param'][] = $userno;
                    $opt['param'][] = $ids_arr;
                }
                break;
            case 'c' : //个人文件柜
                $opt['where'] .= ' and type=1 and userno=?';
                $opt['param'][] = $userno;
                break;
            case 'd' : //公司文件柜
                if ((self::issuperadmin($userno, $enterpriseno) || self::isadmin($userno, $enterpriseno))) {
                    $opt['where'] .= ' and type=0';
                } else {
                    $opt['where'] .= ' and (type=0 and (roles like ? or roles like ? or roles like ?) and !find_in_set(?,directoryproperty))';
                    $opt['param'][] = '%a:0|r|%';
                    $opt['param'][] = '%a:0|r|%';
                    $opt['param'][] = '%u:' . $userno . '|r|%';
                    $opt['param'][] = 2; //隐藏属性
                }

                break;
            case 'e' : //我的共享
                $opt['where'] .= ' and type=1 and isshare=1 and userno=?';
                $opt['param'][] = $userno;
                break;
            case 'f' : //同事的共享
                $ids_arr = self::currentuser_can_see_colleague_documents($userno, $enterpriseno, true);
                $opt['where'] .= ' and type=1 and isshare=1 and userno!=? and find_in_set(documentid,?)';
                $opt['param'][] = $userno;
                $opt['param'][] = $ids_arr;
                break;
        }
        //搜索位置end
        //搜索类型start
        $filetype_res = self::get_filetype_ext($filetype);
        if (count($filetype_res) > 0) {
            if ($filetype == 'other') {
                $opt['where'] .= ' and !find_in_set(extension,?)';
                $opt['param'][] = implode(',', $filetype_res);
            } else {
                $opt['where'] .= ' and isfile=? and find_in_set(extension,?)';
                $opt['param'][] = 1;
                $opt['param'][] = implode(',', $filetype_res);
            }
        }
        //搜索类型end
        //搜索修改时间start
        switch ($fileupdatetime) {
            case 'all' :
                break;
            case 'month' :
                $t = date('t'); //本月天数
                $t--;
                $SDate = date('Y-m-01');
                $EDate = date('Y-m-d', strtotime("+ $t days", strtotime($SDate)));
                $opt['where'] .= ' and (lastupdatedtime between ? and ?)';
                $opt['param'][] = date('Y-m-d 00:00:00', strtotime($SDate));
                $opt['param'][] = date('Y-m-d 23:59:59', strtotime($EDate));
                break;
            case 'week' :
                $w = date('w'); //今天是这周的第几天，0-6
                $SDate = date('Y-m-d', strtotime("- $w days")); //这周开始的日期
                $EDate = date('Y-m-d', strtotime("+6 days", strtotime($SDate))); //这周结束的日期
                $opt['where'] .= ' and (lastupdatedtime between ? and ?)';
                $opt['param'][] = date('Y-m-d 00:00:00', strtotime($SDate));
                $opt['param'][] = date('Y-m-d 23:59:59', strtotime($EDate));
                break;
            case 'today' :
                $opt['where'] .= ' and (lastupdatedtime between ? and ?)';
                $opt['param'][] = date('Y-m-d 00:00:00');
                $opt['param'][] = date('Y-m-d 23:59:59');
                break;
            case 'other' :
                $opt['where'] .= ' and (lastupdatedtime between ? and ?)';
                $opt['param'][] = date('Y-m-d 00:00:00', strtotime($SDate));
                $opt['param'][] = date('Y-m-d 23:59:59', strtotime($EDate));
                break;
        }
        //搜索修改时间end
        //var_dump($opt);

        return $opt;
    }
