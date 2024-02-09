# Data Types for XMLSERVICE and PHP Toolkit #

This reference, begun by Tony Cairns while at IBM, used to be housed at `http://www.youngiprofessionals.com/wiki/index.php/XMLService/DataTypes`, but that site is no longer available, so we have made it available here, and added to it.

## Data Type Equivalents for C / RPG / XMLSERVICE XML / SQL ##

```
C types          RPG types                     XMLSERVICE types                                   SQL types
===============  ============================  ================================================   =========
char             D mychar   32a                <data type='32a'/>                                 CHAR(32)
varchar2         D myvchar2 32a   varying      <data type='32a' varying='on'/>                    VARCHAR(32)
varchar4         D myvchar4 32a   varying(4)   <data type='32a' varying='4'/>
packed           D mydec    12p 2              <data type='12p2'/>                                DECIMAL(12,2)
zoned            D myzone   12s 2              <data type='12s2'/>                                NUMERIC(12,2)
float            D myfloat   4f                <data type='4f2'/>                                 FLOAT
real/double      D myreal    8f                <data type='8f4'/>                                 DOUBLE
binary           D mybin    (any)              <data type='9b'>F1F2F3</data>                      BINARY
hole (no out)    D myhole   (any)              <data type='40h'/>
boolean          D mybool    1n                <data type='4a'/>                                  CHAR(4)
time             D mytime     T   timfmt(*iso) <data type='8A'>09.45.29</data>                    TIME
timestamp        D mystamp    Z                <data type='26A'>2011-12-29-12.45.29.000000</data> TIMESTAMP
date             D mydate     D   datfmt(*iso) <data type='10A'>2009-05-11</data>                 DATE
int8/byte        D myint8    3i 0              <data type='3i0'/>                                 TINYINT   (unsupported DB2)
int16/short      D myint16   5i 0 (4b 0)       <data type='5i0'/>                                 SMALLINT
int32/int        D myint32  10i 0 (9b 0)       <data type='10i0'/>                                INTEGER
int64/longlong   D myint64  20i 0              <data type='20i0'/>                                BIGINT
uint8/ubyte      D myuint8   3u 0              <data type='3u0'/>
uint16/ushort    D myuint16  5u 0              <data type='5u0'/>
uint32/uint      D myuint32 10u 0              <data type='10u0'/>
uint64/ulonglong D myuint64 20u 0              <data type='20u0'/>
[indicator]      D myind     1a                <data type='1a'/>

```
* VARCHAR notes:
  * varchar2: accommodates a string of 1-65535 bytes.
  * varchar4: use if string may be larger than 65535 bytes.
  * https://www.ibm.com/docs/en/i/7.5?topic=keywords-varcharlength-2-4 [https://www.ibm.com/docs/en/i/7.5?topic=keywords-varcharlength-2-4]
  * https://www.ibm.com/docs/en/i/7.5?topic=type-variable-length-character-graphic-ucs-2-formats [https://www.ibm.com/docs/en/i/7.5?topic=type-variable-length-character-graphic-ucs-2-formats]
  


## PHP Toolkit Functions That Implement the XMLSERVICE Data Types Shown Above ### 
 
```
toolkit method/class     type  i/o        comment        varName   value
=======================  ===== ======     =======        ========  =====
$tk->AddParameterChar   (      "both",32,  "char",       "mychar",    "");
$tk->AddParameterChar   (      "both",32,  "varchar2",   "myvchar2",  "",  "on");
$tk->AddParameterChar   (      "both",32,  "varchar4",   "myvchar4",  "",     4);
$tk->AddParameterZoned  (      "both",12,2,"packed",     "mydec",    0.0);
$tk->AddParameterPackDec(      "both",12,2,"zoned",      "myzone",   0.0);
$tk->AddParameterFloat  (      "both",     "float",      "myfloat",  0.0);
$tk->AddParameterReal   (      "both",     "real",       "myreal",   0.0);
$tk->AddParameterBin    (      "both", 9,  "binary",     "mybin", bin2hex(0xF1F2F3)); // to binary pack("H*", $hex)
$tk->AddParameterHole   (             40,  "hole"                       );            // no output (zero input)
$tk->AddParameterChar   (     "both",  4,  "boolean",    "mybool",   "1");
$tk->AddParameterChar   (     "both",  8,  "time",       "mytime",   "09.45.29");
$tk->AddParameterChar   (     "both", 26,  "timestamp",  "mystamp",  "2011-12-29-12.45.29.000000");
$tk->AddParameterChar   (     "both", 10,  "date",       "mydate",   "2009-05-11");
new ProgramParameter    ("3i0","both",     "byte",       "myint8",     0); // work around missing $tk->AddParameterInt8
new ProgramParameter    ("5i0","both",     "short",      "myint16",    0); // work around missing $tk->AddParameterInt16
$tk->AddParameterInt32  (      "both",     "int",        "myint32",    0);
$tk->AddParameterInt64  (      "both",     "longlong",   "myint64",    0);
new ProgramParameter    ("3u0","both",     "ubyte",      "myuint8",    0); // work around missing $tk->AddParameterUInt8
new ProgramParameter    ("5u0","both",     "ushort",     "myuint16",   0); // work around missing $tk->AddParameterUInt16
$tk->AddParameterUInt32 (      "both",     "uint",       "myuint32",   0);
$tk->AddParameterUInt64 (      "both",     "ulonglong",  "myuint64",   0);
$tk->AddParameterChar   (      "out",  1,  "ind '0'/'1'" "myind",     ""); // indicator type is boolean 1-byte character
$tk->AddDataStruct      ([array of parameters],          "myds"         );  

```
